<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $this->referralService->ensureReferralCode($customer);
        $stats = $this->referralService->getReferralStats($customer);

        // Get recent referrals with their status
        $referrals = $customer->referrals()
            ->withCount(['orders' => function ($query) {
                $query->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);
            }])
            ->latest()
            ->paginate(10);

        return view('storefront.account.referrals', compact('stats', 'referrals'));
    }

    public function share()
    {
        $customer = Auth::guard('customer')->user();
        $this->referralService->ensureReferralCode($customer);

        return view('storefront.account.referral-share', compact('customer'));
    }
}
