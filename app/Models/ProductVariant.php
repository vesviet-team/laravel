<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'stock',
        'low_stock_threshold',
        'weight',
        'length',
        'width',
        'height',
        'attributes_json',
        'option_values',
        'is_active',
        'is_purchasable',
        'position',
    ];

    protected $casts = [
        'attributes_json' => 'array',
        'option_values' => 'array',
        'is_active' => 'boolean',
        'is_purchasable' => 'boolean',
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
    ];

    protected $appends = [
        'formatted_price',
        'formatted_compare_at_price',
        'is_in_stock',
        'is_low_stock',
        'has_discount',
        'discount_percentage',
        'dimensions',
        'effective_weight',
        'effective_dimensions',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get effective weight (variant weight if > 0, else product weight).
     */
    public function getEffectiveWeightAttribute(): int
    {
        return $this->weight > 0 ? $this->weight : ($this->product?->weight ?? 0);
    }

    /**
     * Get effective dimensions (variant if set, else product).
     */
    public function getEffectiveDimensionsAttribute(): ?string
    {
        if ($this->length && $this->width && $this->height) {
            return "{$this->length} x {$this->width} x {$this->height} cm";
        }

        return $this->product?->dimensions;
    }

    /**
     * Formatted price in Vietnamese Dong (VND).
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', '.').'₫';
    }

    /**
     * Formatted compare-at price in Vietnamese Dong (VND).
     */
    public function getFormattedCompareAtPriceAttribute(): ?string
    {
        if ($this->compare_at_price) {
            return number_format($this->compare_at_price, 0, ',', '.').'₫';
        }

        return null;
    }

    /**
     * Check if variant has a discount/sale price.
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    /**
     * Discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (! $this->has_discount || ! $this->compare_at_price) {
            return null;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    /**
     * Check if variant is in stock.
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if variant is low stock.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    /**
     * Variant dimensions.
     */
    public function getDimensionsAttribute(): ?string
    {
        if ($this->length && $this->width && $this->height) {
            return "{$this->length} x {$this->width} x {$this->height} cm";
        }

        return null;
    }

    /**
     * Get stock status label.
     */
    public function getStockStatusLabelAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'Hết hàng';
        }

        if ($this->is_low_stock) {
            return "Sắp hết hàng (còn {$this->stock})";
        }

        return "Còn hàng ({$this->stock})";
    }

    /**
     * Check if variant is purchasable (active, purchasable, in stock).
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->is_active
            && $this->is_purchasable
            && $this->is_in_stock
            && $this->product?->is_in_stock ?? false;
    }

    /**
     * Get option label for display (e.g., "Red / Large").
     */
    public function getOptionLabelAttribute(): string
    {
        if (empty($this->option_values)) {
            return $this->name;
        }

        return collect($this->option_values)
            ->map(fn ($value, $key) => ucfirst($key).': '.$value)
            ->implode(' / ');
    }

    /**
     * Get option values as key-value pairs for forms.
     */
    public function getOptionPairsAttribute(): array
    {
        if (empty($this->option_values)) {
            return [];
        }

        return array_map(fn ($value, $key) => [
            'key' => $key,
            'value' => $value,
            'label' => ucfirst($key).': '.$value,
        ], $this->option_values, array_keys($this->option_values));
    }

    /**
     * Resolve image URL for this variant (falls back to product images).
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        // Check variant-specific image in attributes
        if (! empty($this->attributes_json['image'])) {
            return Product::resolveImageUrl($this->attributes_json['image']);
        }

        // Fall back to product image
        return $this->product?->primary_image_url;
    }

    /**
     * Get all images for this variant.
     */
    public function getGalleryImagesAttribute(): array
    {
        $images = [];

        // Variant-specific images
        if (! empty($this->attributes_json['gallery']) && is_array($this->attributes_json['gallery'])) {
            foreach ($this->attributes_json['gallery'] as $item) {
                if ($url = Product::resolveImageUrl($item)) {
                    $images[] = $url;
                }
            }
        }

        // If no variant images, use product images
        if (empty($images)) {
            $images = $this->product?->gallery_images ?? [];
        }

        return $images;
    }
}
