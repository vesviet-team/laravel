<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCartItem;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected string $sessionKey = 'cart';

    /**
     * [I-02] Inject PromotionEngine via constructor — do not call app() per loop iteration.
     * The engine's getActiveCatalogRules() is cache-backed, but re-constructing via app()
     * on every product is wasteful and bypasses constructor-level DI contracts.
     */
    public function __construct(
        protected PromotionEngine $promotionEngine
    ) {}

    public function getCart(): array
    {
        // Logged-in: DB is source of truth; session is a cache.
        // If session has data → cache hit, no DB query.
        // If session is empty → load from DB and warm session.
        if (Auth::guard('customer')->check()) {
            $sessionCart = Session::get($this->sessionKey, []);
            if (! empty($sessionCart)) {
                return $sessionCart;
            }

            return $this->getCartFromDB();
        }

        // Guest: session only (unchanged behavior).
        return Session::get($this->sessionKey, []);
    }

    public function add(int $productId, ?int $variantId, int $quantity = 1): void
    {
        $cart = $this->getCart();
        $key = $this->generateKey($productId, $variantId);

        if (isset($cart[$key])) {
            // D-02 fix: cap total qty at 99 — consistent with mergeGuestCartToDB soft cap.
            $cart[$key]['quantity'] = min($cart[$key]['quantity'] + $quantity, 99);
        } else {
            $cart[$key] = [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => min($quantity, 99),
            ];
        }

        $this->saveCart($cart);
    }

    /**
     * Merge guest session cart into the customer's DB cart after login or register.
     * Quantities are summed and capped at 99 (soft limit).
     * Session cart is cleared after merge — DB becomes the single source of truth.
     */
    public function mergeGuestCartToDB(Customer $customer, array $guestCart): void
    {
        if (empty($guestCart)) {
            return;
        }

        // Load existing DB cart keyed by composite cart key.
        $existing = CustomerCartItem::where('customer_id', $customer->id)
            ->get()
            ->keyBy(fn ($r) => $this->generateKey($r->product_id, $r->product_variant_id));

        $rows = [];
        foreach ($guestCart as $key => $item) {
            $existingQty = $existing->get($key)?->quantity ?? 0;
            $mergedQty = min($existingQty + $item['quantity'], 99); // soft cap
            $rows[] = [
                'customer_id' => $customer->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? 0, // 0 = sentinel for null
                'quantity' => $mergedQty,
                'updated_at' => now(),
            ];
        }

        try {
            CustomerCartItem::upsert(
                $rows,
                ['customer_id', 'product_id', 'product_variant_id'],
                ['quantity', 'updated_at']
            );

            // DB = source of truth. Session will be re-warmed on next getCart() call.
            Session::forget($this->sessionKey);

            Log::info('CartService: guest cart merged to DB.', [
                'customer_id' => $customer->id,
                'items' => count($rows),
            ]);
        } catch (\Throwable $e) {
            // D-03 fix: merge failure must NOT block login.
            // Guest cart remains in session and will be available this session.
            Log::warning('CartService: mergeGuestCartToDB failed — session cart preserved.', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function update(int $productId, ?int $variantId, int $quantity): void
    {
        $cart = $this->getCart();
        $key = $this->generateKey($productId, $variantId);

        if (isset($cart[$key])) {
            if ($quantity <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['quantity'] = $quantity;
            }
            $this->saveCart($cart);
        }
    }

    public function remove(int $productId, ?int $variantId): void
    {
        $cart = $this->getCart();
        $key = $this->generateKey($productId, $variantId);

        if (isset($cart[$key])) {
            unset($cart[$key]);
            $this->saveCart($cart);
        }
    }

    public function clear(): void
    {
        Session::forget($this->sessionKey);
        // Clear DB cart OUTSIDE any transaction (post-commit) —
        // safe: if this fails, the order is already placed; cart will appear stale but won't block.
        if (Auth::guard('customer')->check()) {
            try {
                CustomerCartItem::where('customer_id', Auth::guard('customer')->id())->delete();
            } catch (\Throwable $e) {
                Log::warning('CartService: DB clear failed after checkout.', [
                    'customer_id' => Auth::guard('customer')->id(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Calculate cart total applying flash sale prices where applicable.
     * Batch-loads products, variants, and flash sale items to avoid N+1 queries.
     */
    public function calculateTotal(): float
    {
        $items = $this->getCartItemsDetails();

        return (float) array_sum(array_column($items, 'subtotal'));
    }

    /**
     * Return enriched cart items with price, name, flash sale state, and subtotal.
     * Uses batch queries to avoid N+1 — one query each for products, variants, and flash sale items.
     */
    public function getCartItemsDetails(): array
    {
        $cart = $this->getCart();

        if (empty($cart)) {
            return [];
        }

        // Collect IDs for batch loading
        $productIds = array_unique(array_filter(array_column($cart, 'product_id')));
        $variantIds = array_unique(array_filter(array_column($cart, 'product_variant_id')));

        // Batch load products and variants
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds
            ? ProductVariant::with('product')->whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        // Batch load active flash sale items for all products in cart
        $flashSaleItems = $this->getActiveFlashSaleItems($productIds);

        $items = [];
        foreach ($cart as $key => $item) {
            $productId = $item['product_id'];
            $variantId = $item['product_variant_id'] ?? null;
            $quantity = $item['quantity'];

            $price = 0.0;
            $name = '';
            $sku = '';
            $variantName = null;
            $isFlashSale = false;
            $imagePath = null;

            $slug = null;
            $categoryId = null;
            $originalPrice = 0.0;
            $promotedResult = null;
            $weight = 500;

            if ($variantId && $variants->has($variantId)) {
                $variant = $variants->get($variantId);
                $price = (float) $variant->price;
                $originalPrice = $price;
                $name = $variant->product->name ?? '';
                $variantName = $variant->name;
                $sku = $variant->sku ?? '';
                $imagePath = $variant->product->primary_image_url ?? $variant->product->thumbnail ?? null;
                $slug = $variant->product->slug ?? null;
                $categoryId = $variant->product->category_id ?? null;
                $weight = (int) (($variant->weight > 0 ? $variant->weight : null) ?? ($variant->product?->weight > 0 ? $variant->product->weight : 500));
            } elseif ($products->has($productId)) {
                $product = $products->get($productId);
                $price = (float) $product->price;
                $originalPrice = $price;
                $name = $product->name;
                $sku = $product->sku ?? '';
                $imagePath = $product->primary_image_url ?? $product->thumbnail ?? null;
                $slug = $product->slug ?? null;
                $categoryId = $product->category_id ?? null;
                $weight = (int) ($product->weight > 0 ? $product->weight : 500);

                // [I-02] Use injected engine instance — catalog rules are already cache-backed
                // in getActiveCatalogRules(); no N+1 here since the cache is populated once.
                $promotedResult = $this->promotionEngine->resolveProductPromotedPrice($product);
                if ($promotedResult !== null) {
                    $price = (float) $promotedResult->promotedPrice;
                    $originalPrice = (float) $promotedResult->originalPrice;
                }
            }

            // Override price with flash sale price if applicable (Flash sale takes precedence)
            if (isset($flashSaleItems[$productId])) {
                $price = (float) $flashSaleItems[$productId]->price;
                $isFlashSale = true;
            }

            $items[] = [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'category_id' => $categoryId,
                'product_name' => $name,
                'variant_name' => $variantName,
                'sku' => $sku,
                'price' => $price,
                'original_price' => $originalPrice,
                'weight' => max(100, $weight),
                'quantity' => $quantity,
                'subtotal' => $price * $quantity,
                'is_flash_sale' => $isFlashSale,
                'is_catalog_promoted' => ($promotedResult !== null && ! $isFlashSale),
                'promoted_result' => $promotedResult?->toArray(),
                'image_path' => $imagePath ?? null,
                'slug' => $slug,
            ];
        }

        return $items;
    }

    /**
     * Batch-load active flash sale items for the given product IDs.
     * Returns a keyed collection by product_id.
     */
    private function getActiveFlashSaleItems(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $items = FlashSaleItem::whereIn('product_id', $productIds)
            ->whereHas('flashSale', function ($q) {
                $q->where('status', 'active')
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now());
            })
            ->whereColumn('sold_quantity', '<', 'quantity')
            ->get()
            ->keyBy('product_id');

        return $items->all();
    }

    /**
     * Validate real-time stock availability for all items in the current cart.
     * Returns an array of issues if any, or empty array if all items are in stock.
     */
    public function validateStock(): array
    {
        $cart = $this->getCart();
        if (empty($cart)) {
            return [];
        }

        $productIds = array_column($cart, 'product_id');
        $variantIds = array_filter(array_column($cart, 'product_variant_id'));

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = ! empty($variantIds) ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id') : collect();

        $issues = [];

        foreach ($cart as $key => $item) {
            $productId = $item['product_id'];
            $variantId = $item['product_variant_id'] ?? null;
            $quantity = $item['quantity'];

            if ($variantId && $variants->has($variantId)) {
                $variant = $variants->get($variantId);
                if ($variant->stock < $quantity) {
                    $issues[] = [
                        'name' => ($variant->product->name ?? 'Sản phẩm').' ('.$variant->name.')',
                        'requested' => $quantity,
                        'available' => $variant->stock,
                    ];
                }
            } elseif ($products->has($productId)) {
                $product = $products->get($productId);
                if ($product->stock < $quantity) {
                    $issues[] = [
                        'name' => $product->name,
                        'requested' => $quantity,
                        'available' => $product->stock,
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get a comprehensive structured summary of the cart.
     */
    public function getSummary(): array
    {
        $items = $this->getCartItemsDetails();
        $subtotal = $this->calculateTotal();
        $totalItems = array_sum(array_column($items, 'quantity'));

        return [
            'items' => $items,
            'item_count' => $totalItems,
            'subtotal' => $subtotal,
            'formatted_subtotal' => number_format($subtotal, 0, ',', '.').'₫',
            'is_empty' => empty($items),
        ];
    }

    protected function generateKey(int $productId, ?int $variantId): string
    {
        // Normalize null and 0 to the same key '0' — both mean "no variant".
        // DB stores 0 (sentinel), session stores null; both must produce the same lookup key.
        return $productId.'_'.(($variantId === null || $variantId === 0) ? '0' : $variantId);
    }

    protected function saveCart(array $cart): void
    {
        Session::put($this->sessionKey, $cart);

        // Sync to DB for logged-in customers (best-effort — do not block on failure).
        if (Auth::guard('customer')->check()) {
            $this->syncCartToDB($cart);
        }
    }

    /**
     * Load cart from DB and warm the session cache.
     * Returns the same array shape as the session cart so downstream code is unaffected.
     */
    private function getCartFromDB(): array
    {
        try {
            $cart = [];
            CustomerCartItem::where('customer_id', Auth::guard('customer')->id())
                ->get()
                ->each(function ($row) use (&$cart) {
                    $key = $this->generateKey($row->product_id, $row->product_variant_id);
                    $cart[$key] = [
                        'product_id' => $row->product_id,
                        // Convert sentinel 0 back to null for session cart compatibility
                        'product_variant_id' => $row->product_variant_id === 0 ? null : $row->product_variant_id,
                        'quantity' => $row->quantity,
                    ];
                });

            Session::put($this->sessionKey, $cart); // warm session cache

            return $cart;
        } catch (\Throwable $e) {
            // D-05 fix: DB failure on session miss must not crash the page.
            // Return empty cart — guest-like degraded mode until DB recovers.
            Log::warning('CartService: getCartFromDB failed — returning empty cart.', [
                'customer_id' => Auth::guard('customer')->id(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync the current session cart to DB using a single upsert + composite-key cleanup.
     *
     * TL FIX: cleanup uses composite (product_id, product_variant_id) pairs,
     * NOT just product_id, to avoid deleting other variants of the same product.
     */
    private function syncCartToDB(array $cart): void
    {
        try {
            $customerId = Auth::guard('customer')->id();

            if (empty($cart)) {
                CustomerCartItem::where('customer_id', $customerId)->delete();

                return;
            }

            $rows = collect($cart)->map(fn ($item) => [
                'customer_id' => $customerId,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? 0, // 0 = no variant sentinel
                'quantity' => $item['quantity'],
                'updated_at' => now(),
            ])->values()->all();

            CustomerCartItem::upsert(
                $rows,
                ['customer_id', 'product_id', 'product_variant_id'],
                ['quantity', 'updated_at']
            );

            // Remove rows no longer in cart using composite pairs (TL FIX + NULL sentinel fix).
            $activePairs = collect($cart)->map(fn ($item) => [
                (int) $item['product_id'],
                (int) ($item['product_variant_id'] ?? 0), // 0 = no variant
            ])->all();

            CustomerCartItem::where('customer_id', $customerId)
                ->get()
                ->filter(fn ($row) => ! collect($activePairs)->contains(
                    fn ($pair) => $pair[0] === (int) $row->product_id
                        && $pair[1] === (int) $row->product_variant_id
                ))
                ->each->delete();

        } catch (\Throwable $e) {
            // DB sync must NOT block the user — session cart remains valid.
            Log::warning('CartService: DB sync failed, session cart preserved.', [
                'customer_id' => Auth::guard('customer')->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
