<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $preferences = $customer->notification_preferences ?? [
            'order_updates' => true,
            'promotions' => true,
            'security_alerts' => true,
            'newsletter' => false,
            'sms_order_updates' => false,
            'sms_promotions' => false,
        ];

        return view('storefront.account.notifications', compact('preferences'));
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'order_updates' => ['boolean'],
            'promotions' => ['boolean'],
            'security_alerts' => ['boolean'],
            'newsletter' => ['boolean'],
            'sms_order_updates' => ['boolean'],
            'sms_promotions' => ['boolean'],
        ]);

        // Ensure security alerts cannot be disabled
        $validated['security_alerts'] = true;

        $customer->notification_preferences = array_merge(
            $customer->notification_preferences ?? [],
            $validated
        );
        $customer->save();

        return back()->with('success', 'Cập nhật cài đặt thông báo thành công.');
    }
}
