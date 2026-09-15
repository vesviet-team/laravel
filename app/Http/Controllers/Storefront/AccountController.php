<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * Display customer order history with status tabs and statistics.
     */
    public function orders(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $statusTab = $request->query('status', 'all');

        $query = $customer->orders()
            ->with(['items.product'])
            ->orderBy('created_at', 'desc');

        if ($statusTab === 'processing') {
            $query->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing]);
        } elseif ($statusTab === 'shipped') {
            $query->where('status', OrderStatus::Shipped);
        } elseif ($statusTab === 'delivered') {
            $query->where('status', OrderStatus::Delivered);
        } elseif ($statusTab === 'cancelled') {
            $query->where('status', OrderStatus::Cancelled);
        }

        $orders = $query->paginate(15)->withQueryString();

        // Customer dashboard statistics
        $totalOrdersCount = $customer->orders()->count();
        $deliveringCount = $customer->orders()->where('status', OrderStatus::Shipped)->count();
        $completedCount = $customer->orders()->where('status', OrderStatus::Delivered)->count();
        $totalSpentFormatted = $customer->formatted_total_spent;
        $membershipTier = $customer->membership_tier;
        $membershipTierBadge = $customer->membership_tier_badge_classes;

        return view('storefront.account.orders', compact(
            'orders',
            'customer',
            'statusTab',
            'totalOrdersCount',
            'deliveringCount',
            'completedCount',
            'totalSpentFormatted',
            'membershipTier',
            'membershipTierBadge'
        ));
    }

    /**
     * Display detailed single order view for the authenticated customer.
     */
    public function orderDetail(string $orderNumber)
    {
        $customer = Auth::guard('customer')->user();

        $order = $customer->orders()
            ->where('order_number', $orderNumber)
            ->with(['items.product'])
            ->firstOrFail();

        return view('storefront.account.order-detail', compact('order', 'customer'));
    }

    /**
     * Cancel an eligible order directly from the customer portal.
     */
    public function cancelOrder(string $orderNumber, CancelOrderAction $cancelOrderAction)
    {
        $customer = Auth::guard('customer')->user();

        $order = $customer->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if (! $order->is_cancellable) {
            return back()->with('error', 'Đơn hàng này không thể hủy do đã được xuất kho hoặc đang giao.');
        }

        try {
            $cancelOrderAction->execute($order);

            return back()->with('success', 'Đơn hàng #'.$order->order_number.' đã được hủy thành công.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể hủy đơn hàng: '.$e->getMessage());
        }
    }

    /**
     * 1-Click Reorder: Add items from previous order back into the active cart.
     */
    public function reorder(string $orderNumber, CartService $cartService)
    {
        $customer = Auth::guard('customer')->user();

        $order = $customer->orders()
            ->where('order_number', $orderNumber)
            ->with(['items.product'])
            ->firstOrFail();

        $addedCount = 0;
        foreach ($order->items as $item) {
            if ($item->product && in_array($item->product->status, ['active', 'published']) && $item->product->stock > 0) {
                $cartService->add($item->product_id, $item->product_variant_id, $item->quantity);
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            return redirect()->route('checkout.index')->with('success', "Đã thêm {$addedCount} sản phẩm từ đơn hàng #{$order->order_number} vào giỏ hàng.");
        }

        return back()->with('error', 'Các sản phẩm trong đơn hàng hiện đã hết hàng hoặc không khả dụng.');
    }
}
