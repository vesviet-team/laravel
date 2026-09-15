<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\TwoFactorVerifyRequest;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\ReferralService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected TwoFactorService $twoFactorService;

    protected CartService $cartService;

    public function __construct(TwoFactorService $twoFactorService, CartService $cartService)
    {
        $this->twoFactorService = $twoFactorService;
        $this->cartService = $cartService;
    }

    public function showLoginForm()
    {
        return view('storefront.auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        $this->ensureNotRateLimited($request);

        $customer = Customer::where('email', $credentials['email'])->first();

        if ($customer && $customer->isLocked()) {
            $minutes = $customer->getLockoutRemainingMinutes();
            throw ValidationException::withMessages([
                'email' => "Tài khoản đã bị khóa do đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$minutes} phút.",
            ]);
        }

        if (Auth::guard('customer')->attempt($credentials, $remember)) {
            $this->clearLoginAttempts($request);

            $customer = Auth::guard('customer')->user();
            $customer->resetFailedLoginAttempts();

            if ($remember) {
                $customer->regenerateRememberToken();
            }

            Session::migrate(true);

            // Merge any guest session cart into the customer's DB cart.
            // This ensures items added before login are preserved cross-device.
            $guestCart = session()->get('cart', []);
            if (! empty($guestCart)) {
                $this->cartService->mergeGuestCartToDB(
                    Auth::guard('customer')->user(),
                    $guestCart
                );
            }

            // Check if 2FA is enabled
            if ($customer->two_factor_enabled) {
                // Store the intended URL and redirect to 2FA challenge
                $request->session()->put('2fa:customer_id', $customer->id);
                $request->session()->put('2fa:remember', $remember);
                $request->session()->put('2fa:intended', $request->session()->pull('url.intended', route('account.orders')));

                return redirect()->route('account.2fa.challenge');
            }

            return redirect()->intended(route('account.orders'))
                ->with('success', 'Đăng nhập thành công. Chào mừng quay lại!');
        }

        if ($customer) {
            $customer->incrementFailedLoginAttempts();
        }

        $this->incrementLoginAttempts($request);

        throw ValidationException::withMessages([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ]);
    }

    public function showTwoFactorChallenge()
    {
        if (! session('2fa:customer_id')) {
            return redirect()->route('account.login');
        }

        return view('storefront.auth.two-factor-challenge');
    }

    public function verifyTwoFactor(TwoFactorVerifyRequest $request)
    {
        $customerId = $request->session()->pull('2fa:customer_id');
        $remember = $request->session()->pull('2fa:remember', false);
        $intended = $request->session()->pull('2fa:intended', route('account.orders'));

        if (! $customerId) {
            return redirect()->route('account.login');
        }

        $customer = Customer::find($customerId);

        if (! $customer || ! $customer->two_factor_enabled) {
            return redirect()->route('account.login');
        }

        $verified = false;

        // Try TOTP code first
        if ($request->filled('code')) {
            $verified = $this->twoFactorService->verifyCode($customer, $request->code);
        }

        // Try recovery code
        if (! $verified && $request->filled('recovery_code')) {
            $verified = $this->twoFactorService->verifyRecoveryCode($customer, $request->recovery_code);
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'Mã xác thực hoặc mã khôi phục không chính xác.',
            ]);
        }

        // Complete login
        Auth::guard('customer')->login($customer, $remember);
        Session::migrate(true);

        if ($remember) {
            $customer->regenerateRememberToken();
        }

        // D-01 fix: merge guest cart that was added BEFORE 2FA challenge completed.
        // The actual Auth::login happens here (not in login()), so merge must be here too.
        $guestCart = session()->get('cart', []);
        if (! empty($guestCart)) {
            $this->cartService->mergeGuestCartToDB($customer, $guestCart);
        }

        return redirect()->intended($intended)
            ->with('success', 'Đăng nhập thành công. Chào mừng quay lại!');
    }

    public function showRegisterForm()
    {
        return view('storefront.auth.register');
    }

    public function register(RegisterRequest $request, CartService $cartService, ReferralService $referralService)
    {
        $validated = $request->validated();

        // Get referral code from query parameter
        $referralCode = $request->query('ref');

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        // Process referral if code provided
        if ($referralCode) {
            $referralService->processReferral($customer, $referralCode);
        }

        // Ensure customer has a referral code
        $referralService->ensureReferralCode($customer);

        Auth::guard('customer')->login($customer);
        Session::migrate(true);

        // Merge any guest session cart into the newly registered customer's DB cart.
        $guestCart = session()->get('cart', []);
        if (! empty($guestCart)) {
            $cartService->mergeGuestCartToDB($customer, $guestCart);
        }

        return redirect()->route('account.orders')
            ->with('success', 'Đăng ký tài khoản thành công. Chào mừng bạn đến với cửa hàng!');
    }

    public function showForgotPasswordForm()
    {
        return view('storefront.auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request)
    {
        $this->ensureNotRateLimited($request, 'password_reset');

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Chúng tôi đã gửi liên kết đặt lại mật khẩu vào email của bạn.');
        }

        return back()->withErrors(['email' => 'Không tìm thấy tài khoản với email này.']);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('storefront.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $customer->resetFailedLoginAttempts();

                event(new PasswordReset($customer));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('account.login')
                ->with('status', 'Mật khẩu đã được đặt lại thành công. Vui lòng đăng nhập lại.');
        }

        return back()->withErrors(['email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.']);
    }

    public function logout(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $sessionId = $request->session()->getId();
            $sessions = $customer->active_sessions ?? [];
            $sessions = array_values(array_filter($sessions, fn ($id) => $id !== $sessionId));
            $customer->active_sessions = $sessions;
            $customer->saveQuietly();
        }

        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('products.index')
            ->with('success', 'Bạn đã đăng xuất thành công.');
    }

    protected function ensureNotRateLimited(Request $request, string $key = 'login'): void
    {
        $key = $key.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Quá nhiều lần thử đăng nhập. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }
    }

    protected function incrementLoginAttempts(Request $request, string $key = 'login'): void
    {
        RateLimiter::hit($key.':'.$request->ip(), 60);
    }

    protected function clearLoginAttempts(Request $request, string $key = 'login'): void
    {
        RateLimiter::clear($key.':'.$request->ip());
    }
}
