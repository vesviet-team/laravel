<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;

class ReferralService
{
    public function generateReferralCode(): string
    {
        do {
            $code = 'REF-'.Str::upper(Str::random(8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }

    public function ensureReferralCode(Customer $customer): void
    {
        if (! $customer->referral_code) {
            $customer->referral_code = $this->generateReferralCode();
            $customer->save();
        }
    }

    public function processReferral(Customer $newCustomer, string $referralCode): ?Customer
    {
        $referrer = Customer::where('referral_code', $referralCode)->first();

        if (! $referrer) {
            return null;
        }

        if ($referrer->id === $newCustomer->id) {
            return null;
        }

        if ($newCustomer->referred_by) {
            return null;
        }

        $newCustomer->referred_by = $referrer->id;
        $newCustomer->save();

        $this->awardReferralPoints($referrer);

        return $referrer;
    }

    public function awardReferralPoints(Customer $referrer, int $points = 50000): void
    {
        $referrer->increment('loyalty_points', $points);
    }

    public function awardPurchasePoints(Customer $customer, int $amount): void
    {
        $points = (int) ($amount / 1000);
        if ($points > 0) {
            $customer->increment('loyalty_points', $points);
        }
    }

    public function redeemPoints(Customer $customer, int $points): bool
    {
        if ($customer->loyalty_points < $points) {
            return false;
        }

        $customer->decrement('loyalty_points', $points);

        return true;
    }

    public function getReferralStats(Customer $customer): array
    {
        $referrals = $customer->referrals()->count();
        $completedReferrals = $customer->referrals()
            ->whereHas('orders', function ($query) {
                $query->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);
            })
            ->count();

        return [
            'total_referrals' => $referrals,
            'completed_referrals' => $completedReferrals,
            'loyalty_points' => $customer->loyalty_points,
            'referral_code' => $customer->referral_code,
            'referral_url' => url('/account/register').'?ref='.$customer->referral_code,
        ];
    }
}
