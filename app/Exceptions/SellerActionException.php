<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all Seller Center domain errors.
 *
 * Used by RegisterSellerAction, PublishSellerPageAction, and
 * ProcessSellerQuickOrderAction so callers (Filament resources,
 * Livewire components) can render a single localized error block.
 */
class SellerActionException extends RuntimeException
{
    public function __construct(
        string $message = 'Seller action failed.',
        public readonly string $errorCode = 'seller_action_failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function registrationFailed(Throwable $e): self
    {
        return new self(
            'Đăng ký tài khoản Seller thất bại: '.$e->getMessage(),
            'seller_registration_failed',
            $e,
        );
    }

    public static function pageNotInitialized(): self
    {
        return new self(
            'Trang web chưa được khởi tạo.',
            'seller_page_not_initialized',
        );
    }

    public static function pageUpdateFailed(Throwable $e): self
    {
        return new self(
            'Không thể cập nhật trạng thái trang: '.$e->getMessage(),
            'seller_page_update_failed',
            $e,
        );
    }

    public static function orderFailed(Throwable $e): self
    {
        return new self(
            'Không thể xử lý đơn hàng: '.$e->getMessage(),
            'seller_order_failed',
            $e,
        );
    }

    public static function outOfStock(): self
    {
        return new self(
            'Sản phẩm đã hết hàng hoặc không đủ số lượng.',
            'seller_out_of_stock',
        );
    }

    /**
     * Thrown when an action is attempted on a resource the seller does not own.
     */
    public static function unauthorized(string $detail = 'Không có quyền thực hiện hành động này.'): self
    {
        return new self($detail, 'seller_unauthorized');
    }

    /**
     * Thrown when subdomain generation fails after all retry attempts.
     * This indicates an extreme concurrent registration burst on the same shop name.
     *
     * P2-01: Called from RegisterSellerAction after MAX_SUBDOMAIN_RETRIES exhausted.
     */
    public static function subdomainCollision(string $shopName): self
    {
        return new self(
            "Không thể tạo subdomain cho cửa hàng '{$shopName}'. Vui lòng thử lại sau giây lát.",
            'seller_subdomain_collision',
        );
    }

    /**
     * Thrown when a requested status transition is not permitted by the state machine.
     *
     * @param  string  $from  Current status value.
     * @param  string  $to  Requested status value.
     */
    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(
            "Không thể chuyển đơn hàng từ trạng thái '{$from}' sang '{$to}'.",
            'seller_invalid_status_transition',
        );
    }

    /**
     * Thrown when shop_slug rename collides with an existing slug after all checks.
     * ADR-SC1: Used by AdminUpdateSellerSlugAction on DB UNIQUE constraint violation.
     */
    public static function shopSlugCollision(string $slug): self
    {
        return new self(
            "Slug '{$slug}' đã được sử dụng bởi cửa hàng khác. Vui lòng chọn slug khác.",
            'seller_shop_slug_collision',
        );
    }

    /**
     * Thrown when the provided shop_slug does not match the allowed format [a-z0-9-]+.
     * ADR-SC1: slug format must be consistent with the /shop/{shop_slug} route constraint.
     */
    public static function invalidShopSlugFormat(string $slug): self
    {
        return new self(
            "Slug '{$slug}' không hợp lệ. Chỉ chấp nhận chữ thường (a-z), số (0-9), và dấu gạch nối (-).",
            'seller_invalid_shop_slug_format',
        );
    }
}
