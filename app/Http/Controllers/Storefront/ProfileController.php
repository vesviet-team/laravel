<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\TwoFactorDisableRequest;
use App\Http\Requests\TwoFactorEnableRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function show()
    {
        $customer = Auth::guard('customer')->user();

        return view('storefront.account.profile', compact('customer'));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $validated = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($customer->avatar) {
                Storage::disk('public')->delete($customer->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $customer->update($validated);

        return redirect()->route('account.profile')->with('success', 'Cập nhật hồ sơ thành công.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $customer = Auth::guard('customer')->user();
        $customer->update([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ]);

        $customer->resetFailedLoginAttempts();

        return redirect()->route('account.profile')->with('success', 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.');
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password_confirmation' => ['required', 'current_password:customer'],
        ]);

        $customer = Auth::guard('customer')->user();

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
            'password' => Hash::make(Str::random(32)),
            'remember_token' => null,
            'active_sessions' => [],
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'notification_preferences' => null,
            'privacy_consent' => null,
        ]);

        $customer->addresses()->delete();

        return redirect()->route('products.index')->with('success', 'Tài khoản đã được xóa thành công.');
    }

    // ===========================================
    // Two-Factor Authentication Methods
    // ===========================================

    public function showTwoFactor()
    {
        $customer = Auth::guard('customer')->user();

        return view('storefront.account.two-factor', compact('customer'));
    }

    public function enableTwoFactor(TwoFactorEnableRequest $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        // Generate new secret if not exists
        if (! $customer->two_factor_secret) {
            $this->twoFactorService->regenerateSecret($customer);
            $customer->refresh();
        }

        $enabled = $this->twoFactorService->enable($customer, $request->code);

        if (! $enabled) {
            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không chính xác. Vui lòng thử lại.',
            ]);
        }

        return redirect()->route('account.two-factor')
            ->with('success', 'Đã bật xác thực hai yếu tố (2FA) thành công. Hãy lưu trữ các mã khôi phục an toàn.')
            ->with('recovery_codes', $customer->fresh()->two_factor_recovery_codes);
    }

    public function disableTwoFactor(TwoFactorDisableRequest $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $this->twoFactorService->disable($customer);

        return redirect()->route('account.two-factor')
            ->with('success', 'Đã tắt xác thực hai yếu tố (2FA).');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $customer->two_factor_recovery_codes = $recoveryCodes;
        $customer->save();

        return redirect()->route('account.two-factor')
            ->with('success', 'Đã tạo lại mã khôi phục mới.')
            ->with('recovery_codes', $recoveryCodes);
    }
}
