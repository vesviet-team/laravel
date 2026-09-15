<?php

namespace App\Services;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;

/**
 * CustomerTierResolver — Customer domain service for tier computation.
 *
 * ADR-B1: This service is the ONLY place that computes customer tier.
 * Promotion domain (PromotionRule) must consume the returned CustomerTier enum,
 * not compute tier from raw strings or accessor values directly.
 *
 * Design rationale (I-04):
 *   The old pattern in PromotionRule::matchesCustomerTier() mixed:
 *   - Raw DB attribute access ($customer->getAttributes()['membership_tier'])
 *   - Computed accessor ($customer->membership_tier)
 *   - str_contains() string matching that allowed 'vip_bronze' to match 'vip' gold
 *   - max($rawSpent, $computedSpent) inconsistency between DB and computed values
 *
 * This service provides a single, typed resolution from spend + order history
 * that is testable in isolation, idempotent, and abuse-resistant.
 */
class CustomerTierResolver
{
    /**
     * Resolve the CustomerTier for a given customer based on lifetime spend.
     *
     * Spend thresholds (VND):
     *   - < 5,000,000         → Bronze (new)
     *   - >= 5,000,000        → Silver (loyal)
     *   - >= 20,000,000       → Gold (VIP Diamond)
     *   - >= 50,000,000       → Platinum
     *
     * @param  Customer  $customer  Authenticated customer model.
     * @return CustomerTier Resolved tier enum value.
     */
    public function resolve(Customer $customer): CustomerTier
    {
        $totalSpent = $this->computeLifetimeSpend($customer);

        if ($totalSpent >= 50_000_000) {
            return CustomerTier::Platinum;
        }

        if ($totalSpent >= 20_000_000) {
            return CustomerTier::Gold;
        }

        if ($totalSpent >= 5_000_000) {
            return CustomerTier::Silver;
        }

        return CustomerTier::Bronze;
    }

    /**
     * Determine if a customer is a first-time buyer (zero confirmed orders).
     * Checked against both customer account and email to handle guest orders.
     *
     * @param  Customer|null  $customer  Authenticated customer (nullable for guests).
     * @param  string  $email  Email for guest order lookup.
     * @return bool True if this is the customer's first purchase.
     */
    public function isFirstTime(?Customer $customer, string $email = ''): bool
    {
        $confirmedStatuses = [
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ];

        if ($customer !== null) {
            $hasPriorOrders = $customer->orders()
                ->whereIn('status', $confirmedStatuses)
                ->exists();

            if ($hasPriorOrders) {
                return false;
            }
        }

        $effectiveEmail = trim($email ?: ($customer?->email ?? ''));
        if (! empty($effectiveEmail)) {
            $hasPriorEmailOrders = Order::where('email', $effectiveEmail)
                ->whereIn('status', $confirmedStatuses)
                ->exists();

            if ($hasPriorEmailOrders) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute lifetime spend for a customer from confirmed/active orders.
     * Uses a single aggregation query — does not trigger accessor N+1.
     *
     * @return float Total spend in VND.
     */
    private function computeLifetimeSpend(Customer $customer): float
    {
        return (float) $customer->orders()
            ->whereIn('status', [
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
            ])
            ->sum('total_amount');
    }
}
