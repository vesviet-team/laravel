<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PromotionUsage
 *
 * @property int $id
 * @property int $promotion_rule_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property int|null $order_id
 * @property string $email
 * @property float $discount_amount
 * @property Carbon $created_at
 * @property-read PromotionRule $promotionRule
 * @property-read Customer|null $customer
 * @property-read User|null $user
 * @property-read Order|null $order
 */
class PromotionUsage extends Model
{
    use HasFactory;

    /**
     * Disable standard updated_at timestamp (immutable audit log).
     */
    public $timestamps = false;

    protected $fillable = [
        'promotion_rule_id',
        'customer_id',
        'user_id',
        'order_id',
        'email',
        'discount_amount',
        'created_at',
    ];

    protected $casts = [
        'discount_amount' => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * Auto-populate created_at when creating if not explicitly provided.
     */
    protected static function booted(): void
    {
        static::creating(function (self $usage) {
            if (! $usage->created_at) {
                $usage->created_at = now();
            }
        });
    }

    /**
     * Relationship: The promotion rule that was redeemed.
     */
    public function promotionRule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class, 'promotion_rule_id');
    }

    /**
     * Relationship: The storefront customer who redeemed the rule.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relationship: Optional backoffice user / generic user association.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: The order in which the promotion was applied.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // ==========================================
    // Accessors & Formatting
    // ==========================================

    /**
     * Formatted discount amount in VND.
     */
    public function getFormattedDiscountAmountAttribute(): string
    {
        return number_format($this->discount_amount, 0, ',', '.').'₫';
    }

    /**
     * Display name for the redeeming customer/guest.
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->name ?? $this->order?->customer_name ?? 'Khách vãng lai';
    }
}
