<?php

namespace App\Actions;

use App\Exceptions\SellerActionException;
use App\Models\SellerPage;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Register a new Seller: creates SellerProfile + default SellerPage.
 *
 * CONTRACT: Caller is responsible for providing an already-persisted User.
 * This Action does NOT create the User — it receives one explicitly.
 *
 * ADR-S2: This Action owns the sole DB::transaction() boundary.
 * Services and Models MUST NOT open their own transactions when called from here.
 *
 * SF-09 fix: removed ambiguous auth()->user() fallback; User is now an explicit parameter.
 * SF-03 dependency: the Filament Register page wraps BOTH user creation AND this Action
 *   in a single outer transaction so orphan Users are impossible on SellerProfile failure.
 *
 * P2-01 fix: Added TOCTOU race condition mitigation for subdomain uniqueness.
 *   generateUniqueSubdomain() uses an application-level loop which is not atomic.
 *   Two concurrent registrations with the same shop name can both pass the exists()
 *   check before either commits. The DB UNIQUE constraint (migration line 15) is the
 *   authoritative guard — it throws UniqueConstraintViolationException on collision.
 *   We catch this, regenerate with a random suffix, and retry up to MAX_SUBDOMAIN_RETRIES
 *   times inside a fresh transaction. After retries exhausted, surfaces a user-friendly error.
 *
 * @throws SellerActionException
 */
class RegisterSellerAction
{
    /** Maximum retry attempts when a subdomain collision is detected by the DB. */
    private const MAX_SUBDOMAIN_RETRIES = 3;

    /**
     * Register a new seller and provision their default page within a transaction.
     *
     * @param  User  $user  The already-created User that will own this seller profile.
     * @param  array  $data  Must contain: shop_name, phone. Optionally: email.
     *
     * @throws SellerActionException
     */
    public function execute(User $user, array $data): SellerProfile
    {
        $attempt = 0;

        while ($attempt < self::MAX_SUBDOMAIN_RETRIES) {
            try {
                return DB::transaction(function () use ($user, $data, $attempt) {
                    // On retry attempts, append a short random suffix to break the collision.
                    $subdomain = $attempt === 0
                        ? (new SellerProfile)->generateUniqueSubdomain($data['shop_name'])
                        : (new SellerProfile)->generateUniqueSubdomain($data['shop_name']).'-'.Str::random(4);

                    $sellerProfile = SellerProfile::create([
                        'user_id' => $user->id,
                        'shop_name' => $data['shop_name'],
                        'subdomain' => $subdomain,
                        'shop_slug' => $subdomain, // Slice 1: seeded from subdomain; Admin can rename later
                        'phone' => $data['phone'],
                        'email' => $data['email'] ?? null,
                        'status' => 'active',
                    ]);

                    // Provision a default Seller Page with sensible defaults.
                    SellerPage::create([
                        'seller_id' => $sellerProfile->id,
                        'is_published' => true,
                        'theme_config' => [
                            'primary_color' => '#3b82f6',
                            'font' => 'Inter',
                            'mode' => 'light',
                        ],
                        'blocks' => [
                            [
                                'type' => 'hero',
                                'data' => [
                                    'title' => 'Chào mừng đến với '.$data['shop_name'],
                                    'subtitle' => 'Chuyên cung cấp các sản phẩm chất lượng cao',
                                ],
                            ],
                            [
                                'type' => 'products',
                                'data' => [
                                    'title' => 'Sản phẩm nổi bật',
                                    'limit' => 6,
                                ],
                            ],
                        ],
                    ]);

                    return $sellerProfile;
                });
            } catch (UniqueConstraintViolationException) {
                // P2-01: DB UNIQUE constraint fired — concurrent registration collision.
                // Increment attempt and retry with a fresh random suffix.
                $attempt++;

                if ($attempt >= self::MAX_SUBDOMAIN_RETRIES) {
                    throw SellerActionException::subdomainCollision($data['shop_name']);
                }

                // Small sleep to reduce contention on tight concurrent bursts.
                usleep(50_000); // 50ms
            } catch (Throwable $e) {
                throw SellerActionException::registrationFailed($e);
            }
        }

        // Unreachable — loop always returns or throws. Satisfies static analysis.
        throw SellerActionException::subdomainCollision($data['shop_name']);
    }
}
