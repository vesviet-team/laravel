<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'price_at_purchase',
        'quantity',
        'is_flash_sale',
    ];

    protected $casts = [
        'price_at_purchase' => 'integer',
        'quantity' => 'integer',
        'is_flash_sale' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Subtotal for this line item.
     */
    public function getSubtotalAttribute(): int
    {
        return (int) ($this->price_at_purchase * $this->quantity);
    }

    /**
     * Formatted unit price in VND.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_at_purchase, 0, ',', '.').'₫';
    }

    /**
     * Formatted line item subtotal in VND.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 0, ',', '.').'₫';
    }

    /**
     * Product thumbnail image URL.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->product?->primary_image_url;
    }
}
