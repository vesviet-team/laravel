<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\ProcessCheckoutAction;
use App\Exceptions\CommerceException;
use App\Exceptions\EmptyCartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Province;
use App\Services\CartService;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected ProcessCheckoutAction $processCheckout
    ) {}

    public function index()
    {
        $rawCart = $this->cartService->getCart();

        if (empty($rawCart)) {
            return redirect()->route('products.index')->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        // Enriched items: contains name, price, variant_name, subtotal
        $cart = $this->cartService->getCartItemsDetails();
        $subtotal = $this->cartService->calculateTotal();
        $customer = auth('customer')->user();

        $provinces = Province::orderBy('name')->get();

        $appliedCoupon = session()->get('coupon');

        return view('storefront.checkout.index', compact('cart', 'subtotal', 'customer', 'provinces', 'appliedCoupon'));
    }

    /**
     * Process checkout — delegates entirely to ProcessCheckoutAction.
     * The outer database transaction is removed; ProcessCheckoutAction owns the transaction boundary.
     */
    public function store(CheckoutRequest $request)
    {
        $cart = $this->cartService->getCart();

        if (empty($cart)) {
            return redirect()->route('products.index');
        }

        try {
            $customerData = $request->validated();

            // Attach logged-in customer ID if available.
            if (auth('customer')->check()) {
                $customerData['customer_id'] = auth('customer')->id();
            }

            $couponCode = session()->get('coupon');
            $order = $this->processCheckout->execute($customerData, $couponCode);

            // Clear coupon from session after use.
            session()->forget('coupon');

            // A1: OrderPlaced event dispatched inside ProcessCheckoutAction.
            // SendOrderConfirmationEmail queued listener handles email async — no inline Mail here.

            // Flash session to prevent IDOR on success page
            session()->flash('checkout_completed', $order->order_number);

            return redirect()->route('checkout.success', ['order_number' => $order->order_number]);
        } catch (CommerceException $e) {
            // P1-02: CommerceException messages are user-safe — they come from our domain layer
            // (InsufficientStockException, EmptyCartException, InvalidCouponException, etc.)
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        } catch (\Throwable $e) {
            // P1-02: Generic/system exceptions MUST NOT expose $e->getMessage() to the user
            // (could contain DB errors, stack traces, internal class names — OWASP A05)
            // [B-04] Do NOT log raw cart session data — use only safe metadata (item count).
            Log::error('Checkout failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'cart_item_count' => count(session()->get('cart', [])),
            ]);

            return back()
                ->with('error', 'Lỗi xử lý đơn hàng. Vui lòng thử lại hoặc liên hệ hỗ trợ.')
                ->withInput();
        }
    }

    public function success(string $order_number)
    {
        $order = Order::where('order_number', $order_number)->firstOrFail();

        // Prevent IDOR: Require flash session OR matching authenticated customer
        $isValidSession = session('checkout_completed') === $order_number;
        $isMatchingCustomer = auth('customer')->check() && auth('customer')->id() === $order->customer_id;

        if (! $isValidSession && ! $isMatchingCustomer) {
            abort(403, 'Bạn không có quyền truy cập thông tin đơn hàng này.');
        }

        return view('storefront.checkout.success', compact('order'));
    }
}
