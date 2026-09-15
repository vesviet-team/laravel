<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\View;

class ShortcodeService
{
    /**
     * Parse and replace [product ...] shortcodes in content.
     *
     * Supported formats:
     * - [product id="123"]
     * - [product id=123]
     * - [product sku="LMP-001"]
     * - [product sku=LMP-001]
     * - [product 123]
     */
    public function parse(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Pattern matches [product id="...", sku="...", or [product 123]]
        $pattern = '/\[product(?:\s+(?:id|sku)=["\']?([^"\'\]\s]+)["\']?|\s+([a-zA-Z0-9_\-]+))\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            $identifier = ! empty($matches[1]) ? trim($matches[1]) : (isset($matches[2]) ? trim($matches[2]) : null);

            if (empty($identifier)) {
                return '';
            }

            // Look up product by ID or SKU
            $product = Product::active()
                ->where(function ($q) use ($identifier) {
                    if (is_numeric($identifier)) {
                        $q->where('id', (int) $identifier)->orWhere('sku', $identifier);
                    } else {
                        $q->where('sku', $identifier);
                    }
                })
                ->with(['category'])
                ->first();

            if (! $product) {
                return '';
            }

            if (! View::exists('storefront.blog.partials.product-card-embed')) {
                return '';
            }

            return view('storefront.blog.partials.product-card-embed', compact('product'))->render();
        }, $content);
    }

    /**
     * Strip all shortcodes from text (for plain-text excerpts, meta tags, and RSS feeds).
     */
    public function strip(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $pattern = '/\[product(?:\s+(?:id|sku)=["\']?([^"\'\]\s]+)["\']?|\s+([a-zA-Z0-9_\-]+))\]/i';

        return trim(preg_replace($pattern, '', $content));
    }
}
