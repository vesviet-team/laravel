<?php

namespace App\Services;

use App\Models\Customer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new 2FA secret for the customer.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Get the QR code URL for the customer to scan with their authenticator app.
     */
    public function getQrCodeUrl(Customer $customer): string
    {
        $appName = config('app.name', 'Sober Furniture');

        return $this->google2fa->getQRCodeUrl($appName, $customer->email, $customer->two_factor_secret);
    }

    /**
     * Generate QR code as SVG.
     */
    public function generateQrCodeSvg(Customer $customer): string
    {
        $qrCodeUrl = $this->getQrCodeUrl($customer);

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(300),
                new SvgImageBackEnd
            )
        );

        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Verify a 2FA code.
     */
    public function verifyCode(Customer $customer, string $code): bool
    {
        return $this->google2fa->verifyKey($customer->two_factor_secret, $code);
    }

    /**
     * Generate recovery codes for the customer.
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
        }

        return $codes;
    }

    /**
     * Verify and consume a recovery code.
     */
    public function verifyRecoveryCode(Customer $customer, string $code): bool
    {
        $codes = $customer->two_factor_recovery_codes ?? [];
        $code = strtoupper(trim($code));

        if (($key = array_search($code, $codes)) !== false) {
            unset($codes[$key]);
            $customer->two_factor_recovery_codes = array_values($codes);
            $customer->save();

            return true;
        }

        return false;
    }

    /**
     * Enable 2FA for the customer.
     */
    public function enable(Customer $customer, string $code): bool
    {
        if (! $this->verifyCode($customer, $code)) {
            return false;
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $customer->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return true;
    }

    /**
     * Disable 2FA for the customer.
     */
    public function disable(Customer $customer): void
    {
        $customer->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Regenerate 2FA secret (for re-setup).
     */
    public function regenerateSecret(Customer $customer): void
    {
        $customer->update([
            'two_factor_secret' => $this->generateSecret(),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }
}
