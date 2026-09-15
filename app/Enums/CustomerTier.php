<?php

namespace App\Enums;

/**
 * CustomerTier — Value Object for customer membership tier classification.
 *
 * ADR-B1: This enum is the single source of truth for tier identity.
 * - Customer domain: CustomerTierResolver computes and returns this enum.
 * - Promotion domain: PromotionRule consumes this enum — it does NOT compute tiers.
 *
 * This eliminates the str_contains() abuse vector (I-04) where 'vip_bronze'
 * would incorrectly match 'vip' gold tier conditions.
 *
 * Spend thresholds (VND):
 *   Bronze  → < 5,000,000 (new customer)
 *   Silver  → >= 5,000,000 (loyal member — "Thành Viên Thân Thiết")
 *   Gold    → >= 20,000,000 (VIP Diamond)
 *   Platinum → >= 50,000,000 (reserved for future tier)
 */
enum CustomerTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';
    case FirstTime = 'first_time';

    /**
     * Minimum spend threshold in VND for this tier.
     * Bronze has no minimum (default for new customers).
     */
    public function minSpend(): int
    {
        return match ($this) {
            self::Bronze => 0,
            self::FirstTime => 0,
            self::Silver => 5_000_000,
            self::Gold => 20_000_000,
            self::Platinum => 50_000_000,
        };
    }

    /**
     * Vietnamese display label for UI/Filament.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Thành Viên Mới',
            self::Silver => 'Thành Viên Thân Thiết',
            self::Gold => 'VIP Diamond',
            self::Platinum => 'Platinum',
            self::FirstTime => 'Lần Đầu Mua',
        };
    }

    /**
     * Whether this tier satisfies a required target tier.
     * A higher tier always satisfies a lower tier requirement.
     *
     * This is the safe replacement for str_contains() matching:
     *   - No string substring abuse (e.g. 'vip_bronze' containing 'vip')
     *   - Explicit ordinal comparison, not string heuristics
     */
    public function satisfies(self $required): bool
    {
        return $this->ordinal() >= $required->ordinal();
    }

    /**
     * Ordinal rank for comparison. Higher = more senior tier.
     */
    private function ordinal(): int
    {
        return match ($this) {
            self::FirstTime => 0,
            self::Bronze => 1,
            self::Silver => 2,
            self::Gold => 3,
            self::Platinum => 4,
        };
    }
}
