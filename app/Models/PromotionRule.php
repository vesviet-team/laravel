<?php

namespace App\Models;

use App\Enums\CustomerTier;
use App\Services\CustomerTierResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class PromotionRule
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string $rule_type
 * @property string $action_type
 * @property float $discount_value
 * @property float|null $max_discount_amount
 * @property float $min_order_amount
 * @property int $min_quantity
 * @property array|null $conditions
 * @property string $target_customer_tier
 * @property int|null $usage_limit
 * @property int $usage_limit_per_user
 * @property int $used_count
 * @property int $priority
 * @property bool $stop_further_rules
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PromotionUsage> $usages
 */
class PromotionRule extends Model
{
    use HasFactory;

    // Rule Types Constants
    public const RULE_TYPE_CATALOG = 'catalog_rule';

    public const RULE_TYPE_CART = 'cart_rule';

    // Action Types Constants
    public const ACTION_PERCENTAGE = 'percentage';

    public const ACTION_FIXED_AMOUNT = 'fixed_amount';

    public const ACTION_BUY_X_GET_Y = 'buy_x_get_y';

    public const ACTION_TIERED_QUANTITY = 'tiered_quantity';

    public const ACTION_FREE_SHIPPING = 'free_shipping';

    // Target Customer Tiers Constants
    public const TIER_ALL = 'all';

    public const TIER_BRONZE = 'bronze';

    public const TIER_SILVER = 'silver';

    public const TIER_GOLD = 'gold';

    public const TIER_PLATINUM = 'platinum';

    public const TIER_FIRST_TIME = 'first_time';

    protected $fillable = [
        'name',
        'code',
        'rule_type',
        'action_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'min_quantity',
        'conditions',
        'target_customer_tier',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'priority',
        'stop_further_rules',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $attributes = [
        'rule_type' => self::RULE_TYPE_CART,
        'discount_value' => 0.0,
        'min_order_amount' => 0.0,
        'min_quantity' => 0,
        'target_customer_tier' => self::TIER_ALL,
        'usage_limit_per_user' => 1,
        'used_count' => 0,
        'priority' => 0,
        'stop_further_rules' => false,
        'is_active' => true,
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'min_order_amount' => 'float',
        'min_quantity' => 'integer',
        'conditions' => 'array',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'priority' => 'integer',
        'stop_further_rules' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Usages audit log.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class, 'promotion_rule_id');
    }

    // ==========================================
    // Query Scopes
    // ==========================================

    /**
     * Scope: Active promotion rules within valid schedule window and available usage limit.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->where(function (Builder $q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    /**
     * Scope: Catalog Price Rules only.
     */
    public function scopeCatalogRules(Builder $query): Builder
    {
        return $query->where('rule_type', self::RULE_TYPE_CATALOG);
    }

    /**
     * Scope: Cart Sales Rules only.
     */
    public function scopeCartRules(Builder $query): Builder
    {
        return $query->where('rule_type', self::RULE_TYPE_CART);
    }

    /**
     * Scope: Order by priority ascending (0 = highest priority), secondary by ID.
     */
    public function scopeOrderedByPriority(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('priority', $direction)->orderBy('id', 'asc');
    }

    /**
     * Scope: Find rule by coupon code (case-insensitive).
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->whereRaw('LOWER(code) = ?', [strtolower(trim($code))]);
    }

    /**
     * Scope: Automatic rules (no coupon code required).
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->whereNull('code')->orWhere('code', '');
    }

    // ==========================================
    // Business Logic & Customer Eligibility
    // ==========================================

    /**
     * Determine if this promotion rule is applicable to a given customer, order subtotal, item volume, and items.
     *
     * @param  array<int>  $categoryIds
     * @param  array<int>  $productIds
     */
    public function isApplicableToCustomer(
        ?Customer $customer,
        float $subtotal,
        int $itemCount,
        array $categoryIds = [],
        string $email = '',
        array $productIds = []
    ): bool {
        // 1. Active State Check
        if (! $this->is_active) {
            return false;
        }

        // 2. Date Scheduling Window Check
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        // 3. System-wide Global Usage Limit Check
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        // 4. Minimum Order Subtotal Check
        if ($this->min_order_amount > 0 && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        // 5. Minimum Item Quantity Check
        if ($this->min_quantity > 0 && $itemCount < $this->min_quantity) {
            return false;
        }

        // 6. Target Customer Tier Segmentation Check
        if (! $this->matchesCustomerTier($customer, $email)) {
            return false;
        }

        // 7. JSON Conditions (Category / Product Match) Check
        if (! $this->matchesConditions($categoryIds, $productIds)) {
            return false;
        }

        // 8. Per-User Usage Limit Check against `promotion_usages`
        if (! $this->matchesUserUsageLimit($customer, $email)) {
            return false;
        }

        return true;
    }

    /**
     * Check if customer satisfies the target tier criteria.
     *
     * [I-04/ADR-B1] Refactored: uses CustomerTier enum + CustomerTierResolver
     * instead of fragile str_contains() matching.
     *
     * The old implementation had:
     *   - str_contains($customerTier, 'vip') = TRUE for 'vip_bronze' → gold discount (abuse vector)
     *   - max($rawSpent, $computedSpent) inconsistency between DB raw and computed accessor
     *   - Customer domain computation leaking into Promotion domain model
     *
     * Now: PromotionRule consumes a typed CustomerTier enum — it does not compute tiers.
     */
    protected function matchesCustomerTier(?Customer $customer, string $email = ''): bool
    {
        $targetTier = strtolower(trim((string) $this->target_customer_tier));

        if ($targetTier === '' || $targetTier === self::TIER_ALL) {
            return true;
        }

        // 'first_time' check — uses CustomerTierResolver to evaluate order history
        if ($targetTier === self::TIER_FIRST_TIME) {
            /** @var CustomerTierResolver $resolver */
            $resolver = app(CustomerTierResolver::class);

            return $resolver->isFirstTime($customer, $email);
        }

        // Specific membership tiers require an authenticated customer
        if ($customer === null) {
            return false;
        }

        // Resolve to typed enum via CustomerTierResolver — no str_contains heuristics
        /** @var CustomerTierResolver $resolver */
        $resolver = app(CustomerTierResolver::class);
        $customerTier = $resolver->resolve($customer);

        // Map the string target tier constant to the typed enum
        $requiredTier = match ($targetTier) {
            self::TIER_BRONZE => CustomerTier::Bronze,
            self::TIER_SILVER => CustomerTier::Silver,
            self::TIER_GOLD,
            'vip_gold' => CustomerTier::Gold,
            self::TIER_PLATINUM => CustomerTier::Platinum,
            default => null,
        };

        if ($requiredTier === null) {
            // Unknown tier target — fail safe (deny rather than grant)
            return false;
        }

        // Enum-based ordinal comparison: Gold satisfies Silver, Silver satisfies Bronze, etc.
        return $customerTier->satisfies($requiredTier);
    }

    /**
     * Check if category/product IDs in cart/catalog match JSON condition rules.
     *
     * @param  array<int>  $categoryIds
     * @param  array<int>  $productIds
     */
    protected function matchesConditions(array $categoryIds, array $productIds): bool
    {
        if (empty($this->conditions) || ! is_array($this->conditions)) {
            return true;
        }

        // Category filter matching
        if (! empty($this->conditions['category_ids']) && is_array($this->conditions['category_ids'])) {
            $requiredCategoryIds = array_map('intval', $this->conditions['category_ids']);
            if (empty($categoryIds)) {
                return false;
            }
            $intersect = array_intersect(array_map('intval', $categoryIds), $requiredCategoryIds);
            if (empty($intersect)) {
                return false;
            }
        }

        // Product filter matching
        if (! empty($this->conditions['product_ids']) && is_array($this->conditions['product_ids'])) {
            $requiredProductIds = array_map('intval', $this->conditions['product_ids']);
            if (empty($productIds)) {
                return false;
            }
            $intersect = array_intersect(array_map('intval', $productIds), $requiredProductIds);
            if (empty($intersect)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check per-user usage limits against promotion_usages records.
     */
    protected function matchesUserUsageLimit(?Customer $customer, string $email = ''): bool
    {
        $limit = $this->usage_limit_per_user;

        if ($limit === null || $limit <= 0) {
            return true;
        }

        $effectiveEmail = ! empty($email) ? trim($email) : ($customer?->email ? trim($customer->email) : '');

        // If neither customer nor email is available, cannot evaluate per-user limit
        if ($customer === null && empty($effectiveEmail)) {
            return true;
        }

        $usageQuery = $this->usages();

        $usageQuery->where(function (Builder $q) use ($customer, $effectiveEmail) {
            if ($customer !== null) {
                $q->where('customer_id', $customer->id)
                    ->orWhere('user_id', $customer->id);
            }
            if (! empty($effectiveEmail)) {
                $q->orWhere('email', $effectiveEmail);
            }
        });

        $userUsageCount = $usageQuery->count();

        return $userUsageCount < $limit;
    }

    /**
     * Atomically record a usage instance for this promotion rule and increment used_count.
     */
    public function recordUsage(
        ?int $customerId,
        ?int $orderId,
        string $email,
        float $discountAmount
    ): PromotionUsage {
        // Direct DB increment without model events to prevent unnecessary catalog cache purging
        static::withoutEvents(function () {
            $this->increment('used_count');
        });

        return $this->usages()->create([
            'customer_id' => $customerId,
            'user_id' => null,
            'order_id' => $orderId,
            'email' => trim($email),
            'discount_amount' => $discountAmount,
            'created_at' => now(),
        ]);
    }

    // ==========================================
    // Domain Helpers & Accessors
    // ==========================================

    public function isCatalogRule(): bool
    {
        return $this->rule_type === self::RULE_TYPE_CATALOG;
    }

    public function isCartRule(): bool
    {
        return $this->rule_type === self::RULE_TYPE_CART;
    }

    public function isCoupon(): bool
    {
        return ! empty($this->code);
    }

    public function isAutomatic(): bool
    {
        return empty($this->code);
    }

    /**
     * Formatted discount display string (e.g., "-15%" or "-300.000₫").
     */
    public function getFormattedDiscountAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_PERCENTAGE => (float) $this->discount_value.'%',
            self::ACTION_FIXED_AMOUNT => number_format($this->discount_value, 0, ',', '.').'₫',
            self::ACTION_FREE_SHIPPING => 'Freeship',
            self::ACTION_BUY_X_GET_Y => 'BXGY Tặng Quà',
            self::ACTION_TIERED_QUANTITY => 'Bậc Thang '.(float) $this->discount_value.'%',
            default => (string) $this->discount_value,
        };
    }

    /**
     * Vietnamese label for rule_type.
     */
    public function getRuleTypeLabelAttribute(): string
    {
        return match ($this->rule_type) {
            self::RULE_TYPE_CATALOG => 'Khuyến Mãi Danh Mục / Giá',
            self::RULE_TYPE_CART => 'Khuyến Mãi Giỏ Hàng & Coupon',
            default => $this->rule_type,
        };
    }

    /**
     * Vietnamese label for action_type.
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_PERCENTAGE => 'Giảm phần trăm (%)',
            self::ACTION_FIXED_AMOUNT => 'Giảm số tiền cố định (₫)',
            self::ACTION_BUY_X_GET_Y => 'Mua X Tặng Y (BXGY)',
            self::ACTION_TIERED_QUANTITY => 'Chiết khấu bậc thang số lượng',
            self::ACTION_FREE_SHIPPING => 'Miễn phí vận chuyển (Freeship)',
            default => $this->action_type,
        };
    }

    /**
     * Vietnamese badge label for target customer tier.
     */
    public function getTargetCustomerTierLabelAttribute(): string
    {
        return match ($this->target_customer_tier) {
            self::TIER_ALL => 'Tất cả khách hàng',
            self::TIER_FIRST_TIME => 'Khách hàng mới (Đơn đầu tiên)',
            self::TIER_BRONZE => 'Hạng Đồng (Bronze)',
            self::TIER_SILVER => 'Hạng Bạc (Silver)',
            self::TIER_GOLD => 'Hạng Vàng (Gold / VIP)',
            self::TIER_PLATINUM => 'Hạng Bạch Kim (Platinum)',
            default => ucfirst($this->target_customer_tier),
        };
    }
}
