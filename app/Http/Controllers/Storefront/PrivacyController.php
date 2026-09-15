<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivacyController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function index()
    {
        $customer = Auth::guard('customer')->user();
        $auditLogs = $this->auditLogService->getAuditLogs($customer, 20);

        return view('storefront.account.privacy', compact('customer', 'auditLogs'));
    }

    public function exportData(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        $this->auditLogService->logDataExport($customer, $request);

        $data = [
            'personal_information' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'gender' => $customer->gender,
                'avatar' => $customer->avatar ? Storage::url($customer->avatar) : null,
                'status' => $customer->status,
                'created_at' => $customer->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $customer->updated_at?->format('Y-m-d H:i:s'),
                'email_verified_at' => $customer->email_verified_at?->format('Y-m-d H:i:s'),
            ],
            'addresses' => $customer->addresses->map(function ($address) {
                return [
                    'type' => $address->type,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'district' => $address->district,
                    'ward' => $address->ward,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'is_default' => $address->is_default,
                    'formatted_address' => $address->formatted_address,
                ];
            }),
            'orders' => $customer->orders->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'status' => $order->status->value ?? $order->status,
                    'total_amount' => $order->total_amount,
                    'formatted_total_amount' => $order->formatted_total_amount,
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'variant_name' => $item->variant_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price_at_purchase,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                ];
            }),
            'wishlists' => $customer->wishlists->map(function ($wishlist) {
                return [
                    'product_id' => $wishlist->product_id,
                    'product_variant_id' => $wishlist->product_variant_id,
                    'created_at' => $wishlist->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'reviews' => $customer->reviews->map(function ($review) {
                return [
                    'product_id' => $review->product_id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'referral_info' => [
                'referral_code' => $customer->referral_code,
                'referred_by' => $customer->referred_by ? Customer::find($customer->referred_by)?->email : null,
                'loyalty_points' => $customer->loyalty_points,
            ],
            'security_settings' => [
                'two_factor_enabled' => $customer->two_factor_enabled,
                'two_factor_confirmed_at' => $customer->two_factor_confirmed_at?->format('Y-m-d H:i:s'),
            ],
            'notification_preferences' => $customer->notification_preferences,
            'privacy_consent' => $customer->privacy_consent,
            'audit_logs' => $customer->auditLogs->map(function ($log) {
                return [
                    'action' => $log->action,
                    'description' => $log->description,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ];

        $filename = 'gdpr-export-'.$customer->id.'-'.now()->format('Y-m-d').'.json';

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password_confirmation' => ['required', 'current_password:customer'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $customer = Auth::guard('customer')->user();

        $this->auditLogService->logAccountDeletion($customer, $request);

        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $customer->update([
            'name' => 'Người dùng đã xóa',
            'email' => 'deleted_'.$customer->id.'@example.com',
            'phone' => null,
            'avatar' => null,
            'date_of_birth' => null,
            'gender' => null,
            'status' => 'deleted',
            'password' => bcrypt(Str::random(32)),
            'remember_token' => null,
            'active_sessions' => [],
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'notification_preferences' => null,
            'privacy_consent' => null,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $customer->addresses()->delete();

        return redirect()->route('products.index')->with('success', 'Tài khoản đã được xóa thành công theo quy định GDPR. Dữ liệu cá nhân đã được ẩn danh.');
    }
}
