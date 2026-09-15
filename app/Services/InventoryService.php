<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Deduct stock for all items in the given order.
     *
     * IMPORTANT: This method MUST be called within an active database transaction.
     * It uses lockForUpdate() on rows — these locks are only effective inside a transaction.
     * The transaction is owned by the calling Action (ProcessCheckoutAction, etc.).
     *
     * Items are sorted by product/variant ID to enforce consistent lock ordering
     * and prevent deadlocks when multiple orders process concurrently.
     *
     * @throws RuntimeException on insufficient stock — caller transaction will roll back.
     */
    public function deductStock(Order $order): void
    {
        // Sort items by composite key to guarantee lock ordering (deadlock prevention)
        $items = $order->items->sortBy(function ($item) {
            return $item->product_id.'-'.($item->product_variant_id ?? '0');
        });

        foreach ($items as $item) {
            // Deduct Flash Sale stock if applicable
            if ($item->is_flash_sale) {
                $flashSaleItem = FlashSaleItem::where('product_id', $item->product_id)
                    ->whereHas('flashSale', function ($q) {
                        $q->where('status', 'active');
                    })
                    ->lockForUpdate()
                    ->first();

                if ($flashSaleItem) {
                    if ($flashSaleItem->sold_quantity + $item->quantity > $flashSaleItem->quantity) {
                        throw new InsufficientStockException("Hết hàng flash sale: {$item->product_name}");
                    }
                    $flashSaleItem->increment('sold_quantity', $item->quantity);
                }
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::lockForUpdate()->find($item->product_variant_id);

                if (! $variant || $variant->stock < $item->quantity) {
                    throw new InsufficientStockException("Không đủ tồn kho cho sản phẩm: {$item->product_name}");
                }
                $variant->decrement('stock', $item->quantity);
            } else {
                $product = Product::lockForUpdate()->find($item->product_id);

                if (! $product || $product->stock < $item->quantity) {
                    throw new InsufficientStockException("Không đủ tồn kho cho sản phẩm: {$item->product_name}");
                }
                $product->decrement('stock', $item->quantity);
            }
        }
    }

    /**
     * Restore stock for all items in the given order (e.g. when order is cancelled).
     *
     * IMPORTANT: This method MUST be called within an active database transaction.
     * The transaction is owned by the calling Action (CancelOrderAction, etc.).
     */
    public function restoreStock(Order $order): void
    {
        $items = $order->items->sortBy(function ($item) {
            return $item->product_id.'-'.($item->product_variant_id ?? '0');
        });

        foreach ($items as $item) {
            if ($item->is_flash_sale) {
                $flashSaleItem = FlashSaleItem::where('product_id', $item->product_id)
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                if ($flashSaleItem) {
                    $flashSaleItem->decrement('sold_quantity', $item->quantity);
                }
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::lockForUpdate()->find($item->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                }
            } else {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }

        Log::info('Stock restored for order', ['order_id' => $order->id, 'order_number' => $order->order_number]);
    }
}
