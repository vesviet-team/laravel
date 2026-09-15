<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'failed_login_attempts',
        'locked_until',
        'avatar',
        'date_of_birth',
        'gender',
        'referral_code',
        'referred_by',
        'loyalty_points',
        'email_verified_at',
        'notification_preferences',
        'privacy_consent',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        // [I-09] stripe_customer_id is NOT included — Stripe package not yet integrated.
        // Add back only when Stripe integration is approved via ADR and migration adds the column.
        // 'stripe_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'password' => 'hashed',
        'locked_until' => 'datetime',
        'active_sessions' => 'array',
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'notification_preferences' => 'array',
        'privacy_consent' => 'array',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_recovery_codes' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Customer addresses relationship.
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Default shipping address.
     */
    public function defaultShippingAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('type', 'shipping')->where('is_default', true);
    }

    /**
     * Default billing address.
     */
    public function defaultBillingAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('type', 'billing')->where('is_default', true);
    }

    /**
     * Referrer relationship (who referred this customer).
     */
    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    /**
     * Referrals relationship (customers this customer referred).
     */
    public function referrals()
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    /**
     * Audit logs relationship.
     */
    public function auditLogs()
    {
        return $this->hasMany(CustomerAuditLog::class);
    }

    /**
     * Total lifetime spent on successful / active orders.
     */
    public function getTotalSpentAttribute(): int
    {
        return (int) $this->orders()
            ->whereIn('status', [
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
            ])
            ->sum('total_amount');
    }

    /**
     * Formatted total lifetime spend in VND.
     */
    public function getFormattedTotalSpentAttribute(): string
    {
        return number_format($this->total_spent, 0, ',', '.').'₫';
    }

    /**
     * Membership Tier calculation based on lifetime spend.
     */
    public function getMembershipTierAttribute(): string
    {
        $spent = $this->total_spent;

        if ($spent >= 20000000) {
            return 'VIP Diamond';
        }

        if ($spent >= 5000000) {
            return 'Thành Viên Thân Thiết';
        }

        return 'Thành Viên Mới';
    }

    /**
     * Tailwind CSS badge styling for Membership Tier.
     */
    public function getMembershipTierBadgeClassesAttribute(): string
    {
        $tier = $this->membership_tier;

        return match ($tier) {
            'VIP Diamond' => 'bg-[#23232C] text-white border border-[#23232C]',
            'Thành Viên Thân Thiết' => 'bg-amber-100 text-amber-900 border border-amber-300',
            default => 'bg-[#F0F0F0] text-[#23232C] border border-[#E5E5E5]',
        };
    }

    /**
     * Resolve latest known shipping address from the customer's previous orders.
     */
    public function getLastShippingAddressAttribute(): ?array
    {
        $latestOrder = $this->orders()->latest()->first();

        if (! $latestOrder) {
            return null;
        }

        return [
            'customer_name' => $latestOrder->customer_name ?? $this->name,
            'phone' => $latestOrder->phone ?? $this->phone,
            'email' => $latestOrder->email ?? $this->email,
            'address' => $latestOrder->address,
            'city' => $latestOrder->city,
            'district' => $latestOrder->district,
            'ward' => $latestOrder->ward,
        ];
    }

    /**
     * Get recent orders with eager loaded items.
     */
    public function getRecentOrders(int $limit = 5)
    {
        return $this->orders()
            ->with(['items.product'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Regenerate the remember token for the customer.
     * This should be called on login, password change, and sensitive actions.
     */
    public function regenerateRememberToken(): string
    {
        $this->remember_token = Str::random(60);
        $this->save();

        return $this->remember_token;
    }

    /**
     * Check if the remember token has been rotated (for security auditing).
     */
    public function hasValidRememberToken(string $token): bool
    {
        return $this->remember_token === $token;
    }

    /**
     * Invalidate all remember tokens (logout from all devices).
     */
    public function invalidateAllRememberTokens(): void
    {
        $this->remember_token = null;
        $this->save();
    }

    /**
     * Get the number of active sessions for this customer.
     * This is a placeholder for concurrent session tracking.
     */
    public function getActiveSessionsCount(): int
    {
        // In a real implementation, this would query the sessions table
        // filtered by customer_id. For now, return 1 as baseline.
        return 1;
    }

    /**
     * Check if the account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Increment the failed login attempts counter.
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->failed_login_attempts += 1;

        // Lock account after 5 failed attempts for 15 minutes
        if ($this->failed_login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
        }

        $this->save();
    }

    /**
     * Reset the failed login attempts counter.
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    /**
     * Get the remaining lockout time in minutes.
     */
    public function getLockoutRemainingMinutes(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(1, (int) $this->locked_until->diffInMinutes(now()));
    }
}
