<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human-readable Vietnamese label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Processing => 'Đang chuẩn bị',
            self::Shipped => 'Đang giao hàng',
            self::Delivered => 'Đã giao hàng',
            self::Cancelled => 'Đã huỷ',
        };
    }

    /**
     * Tailwind CSS badge color for Filament tables.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Processing => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Valid next statuses from the current one.
     * Enforces one-way state machine: no reverting delivered/cancelled orders.
     *
     * @return OrderStatus[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    /**
     * Whether transitioning to the given status is valid from the current state.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), strict: true);
    }

    /**
     * Whether this is a terminal state (no further transitions possible).
     */
    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Cancelled;
    }
}
