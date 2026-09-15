<?php

namespace App\Observers;

use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Invalidate homepage caches when a product is created, updated, or deleted.
     * Covers: is_featured toggle, status change, price/name updates.
     */
    public function created(Product $product): void
    {
        $this->clearHomepageCache();
    }

    public function updated(Product $product): void
    {
        $this->clearHomepageCache();

        // Fix P1: Flash Sale Data Integrity
        // If the product price is lowered below an active flash sale price, the flash sale becomes invalid.
        if ($product->wasChanged('price')) {
            FlashSaleItem::where('product_id', $product->id)
                ->where('price', '>=', $product->price)
                ->delete();
        }
    }

    public function deleted(Product $product): void
    {
        $this->clearHomepageCache();
    }

    private function clearHomepageCache(): void
    {
        Cache::forget('homepage.featured');
        Cache::forget('homepage.new_arrivals');
        Cache::forget('homepage.categories');
    }
}
