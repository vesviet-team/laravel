<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'customer_id',
        'seller_id',
        'order_number',
        'status',
        'waybill_id',
        'payment_method',
        'customer_name',
        'phone',
        'email',
        'address',
        'city',
        'district',
        'ward',
        'notes',
        'subtotal',
        'discount_amount',
        'shipping_fee',
        'total_amount',
        'utm_source',
        'landing_page_id',
        'payment_status',
        'payment_transaction_id',
        'paid_at',
        'payment_expires_at',
        'payment_details',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'shipping_fee' => 'integer',
        'total_amount' => 'integer',
        'paid_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'payment_details' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class)->latest('id');
    }

    public function landingPage()
    {
        return $this->belongsTo(LandingPage::class);
    }

    /**
     * Formatted subtotal in VND.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 0, ',', '.').'₫';
    }

    /**
     * Formatted discount amount in VND.
     */
    public function getFormattedDiscountAmountAttribute(): string
    {
        return number_format($this->discount_amount, 0, ',', '.').'₫';
    }

    /**
     * Formatted shipping fee in VND.
     */
    public function getFormattedShippingFeeAttribute(): string
    {
        return number_format($this->shipping_fee, 0, ',', '.').'₫';
    }

    /**
     * Formatted total order amount in VND.
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 0, ',', '.').'₫';
    }

    /**
     * Human-readable status label in Vietnamese.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status instanceof OrderStatus) {
            return $this->status->label();
        }

        return OrderStatus::tryFrom((string) $this->status)?->label() ?? (string) $this->status;
    }

    /**
     * Tailwind CSS badge background & text class for storefront display.
     */
    public function getStatusBadgeClassesAttribute(): string
    {
        $status = $this->status instanceof OrderStatus ? $this->status : OrderStatus::tryFrom((string) $this->status);

        return match ($status) {
            OrderStatus::Pending => 'bg-amber-50 text-amber-700 border border-amber-200',
            OrderStatus::Confirmed => 'bg-blue-50 text-blue-700 border border-blue-200',
            OrderStatus::Processing => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
            OrderStatus::Shipped => 'bg-purple-50 text-purple-700 border border-purple-200',
            OrderStatus::Delivered => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            OrderStatus::Cancelled => 'bg-rose-50 text-rose-700 border border-rose-200',
            default => 'bg-gray-50 text-gray-700 border border-gray-200',
        };
    }

    /**
     * Determine if order can be cancelled by customer.
     */
    public function getIsCancellableAttribute(): bool
    {
        $status = $this->status instanceof OrderStatus ? $this->status : OrderStatus::tryFrom((string) $this->status);

        return in_array($status, [OrderStatus::Pending, OrderStatus::Confirmed]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Đã thanh toán',
            'partially_paid' => 'Thanh toán một phần',
            'failed' => 'Thanh toán thất bại',
            'refunded' => 'Đã hoàn tiền',
            'expired' => 'Hết hạn thanh toán',
            default => 'Chờ thanh toán',
        };
    }

    public function getPaymentStatusBadgeClassesAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'partially_paid' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'failed' => 'bg-rose-50 text-rose-700 border border-rose-200',
            'refunded' => 'bg-purple-50 text-purple-700 border border-purple-200',
            'expired' => 'bg-gray-50 text-gray-700 border border-gray-200',
            default => 'bg-amber-50 text-amber-700 border border-amber-200',
        };
    }

    /**
     * Total count of physical items in order.
     */
    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Scope: orders for a specific customer.
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: recent orders.
     */
    public function scopeRecent($query)
    {
        return $query->latest();
    }
}
