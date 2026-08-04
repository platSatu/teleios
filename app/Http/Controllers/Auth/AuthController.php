<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\HistoryUserLogin;
use App\Models\User;
use App\Services\GolangAuthService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Consolidated replacement for the split Auth\AuthenticatedSessionController
 * / RegisteredUserController / PasswordResetLinkController /
 * NewPasswordController / VerifyEmailController /
 * EmailVerificationNotificationController /
 * EmailVerificationPromptController (all deleted — every bit of their
 * logic now lives here). ConfirmablePasswordController and
 * PasswordController (change-password-while-logged-in /
 * confirm-password-before-a-sensitive-action) are a separate concern
 * from login/register/forgot-password/verify-email and were left as-is.
 *
 * Business rules layered on top of stock Breeze behaviour:
 *
 *   - login(): only users.status === 'active' may authenticate. The
 *     email/password lookup itself is a single Eloquent ->where('email',
 *     $value) — always a parameterized query under the hood (PDO bound
 *     parameters), never raw/concatenated SQL, so it's not
 *     injectable regardless of what's typed into the email field.
 *   - register(): account is created status='inactive' (the column's own
 *     DB default) and is NOT auto-logged-in — since login now requires
 *     'active', staying "logged in" while inactive would be
 *     contradictory. A verification email is queued instead (see
 *     App\Notifications\VerifyEmailNotification /
 *     User::sendCustomVerificationEmail()).
 *   - verifyEmail(): looks up the token column (not a signed URL) so an
 *     expired one can be told apart from an invalid one — and an
 *     expired click auto-resends a fresh link rather than dead-ending.
 *   - forgotPassword(): explicitly checks the email is registered before
 *     sending anything (this app wants that distinct message, rather
 *     than a privacy-preserving generic response).
 *   - Every terminal step (register success, verify success/expired,
 *     forgot-password sent, reset-password success, logout) redirects to
 *     route('login').
 *   - Both email notifications (VerifyEmailNotification,
 *     ResetPasswordNotification) implement ShouldQueue, so sending mail
 *     never blocks the request — see `php artisan queue:work`.
 */
class AuthController extends Controller
{
    public function __construct(
        protected GolangAuthService $golangAuth,
    ) {}

    // =========================================================
    // LOGIN
    // =========================================================

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // Single parameterized lookup by email. Eloquent/query builder
        // binds every value through PDO prepared statements — user
        // input is never interpolated into the SQL string itself — so
        // this can't be manipulated into a different query no matter
        // what's submitted in the email field.
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Correct credentials but not an active account — a deliberately
        // different message from "invalid credentials" above, since the
        // person typing them already proved they know the password.
        if ($user->status !== 'active') {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Akun Anda belum aktif. Silakan verifikasi email Anda terlebih dahulu sebelum login.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Sync with the Go backend so the user also gets a JWT for
        // WhatsApp-related API calls (see ConnectDeviceService). Treated
        // as non-fatal: if the Go backend is unreachable, the web app
        // keeps working and only WhatsApp features are unavailable
        // until the next successful login.
        try {
            $token = $this->golangAuth->login($validated['email'], $validated['password']);
            session(['golang_jwt_token' => $token]);
        } catch (\Throwable $e) {
            report($e);
        }

        HistoryUserLogin::create([
            'user_id' => $user->id,
            'last_login' => now(),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->closeOpenLoginHistory($user->id);
        }

        Auth::logout();

        $request->session()->forget('golang_jwt_token');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // =========================================================
    // REGISTER
    // =========================================================

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            // Explicit even though it matches the column's own DB
            // default — makes the "starts inactive" rule visible here,
            // not just in the migration.
            'status' => 'inactive',
        ]);

        $user->sendCustomVerificationEmail();

        return redirect()->route('login')
            ->with('status', 'Registrasi berhasil! Silakan cek email Anda untuk mengaktifkan akun sebelum login.');
    }

    // =========================================================
    // EMAIL VERIFICATION
    // =========================================================

    public function verifyEmail(string $token): RedirectResponse
    {
        $user = User::where('email_verification_token', $token)->first();

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Link verifikasi tidak valid.']);
        }

        if ($user->status === 'active') {
            return redirect()->route('login')
                ->with('status', 'Email Anda sudah terverifikasi. Silakan login.');
        }

        if (! $user->email_verification_expires_at || $user->email_verification_expires_at->isPast()) {
            // Expired: auto-resend a fresh link rather than dead-ending
            // on an error the user has no way to act on.
            $user->sendCustomVerificationEmail();

            return redirect()->route('login')
                ->with('status', 'Link verifikasi sudah kedaluwarsa. Kami telah mengirimkan link verifikasi baru ke email Anda.');
        }

        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return redirect()->route('login')
            ->with('status', 'Email berhasil diverifikasi. Silakan login.');
    }

    /**
     * Guest-accessible "send me a new verification link" form — needed
     * because an inactive user can never log in to reach an
     * authenticated "please verify" prompt (unlike stock Breeze, where
     * this page only ever shows up post-login). See resources/views/
     * auth/verify-email.blade.php.
     */
    public function showResendVerification(): View
    {
        return view('auth.verify-email');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Same message regardless of whether the email exists or is
        // already active — this endpoint can't be used to enumerate
        // registered addresses.
        if ($user && $user->status !== 'active') {
            $user->sendCustomVerificationEmail();
        }

        return redirect()->route('verification.notice')
            ->with('status', 'Jika email tersebut terdaftar dan belum aktif, link verifikasi baru telah dikirim.');
    }

    // =========================================================
    // FORGOT / RESET PASSWORD
    // =========================================================

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Explicitly checked up front, rather than only relying on
        // Password::sendResetLink()'s own generic INVALID_USER status —
        // this app wants a distinct "email tidak terdaftar" message here.
        if (! User::where('email', $validated['email'])->exists()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email tersebut tidak terdaftar.']);
        }

        // Queued: User::sendPasswordResetNotification() is overridden to
        // dispatch App\Notifications\ResetPasswordNotification (implements
        // ShouldQueue) instead of Laravel's default synchronous one.
        $status = Password::sendResetLink($validated);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')
            ->with('status', 'Link reset password telah dikirim ke email Anda.');
    }

    public function showResetPassword(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($validated) {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Closes the user's currently-open login history entry (if any) and
     * records how long the session lasted.
     */
    private function closeOpenLoginHistory(string $userId): void
    {
        $history = HistoryUserLogin::where('user_id', $userId)
            ->whereNull('last_logout')
            ->latest()
            ->first();

        if (! $history || ! $history->last_login) {
            return;
        }

        $history->update([
            'last_logout' => now(),
            'duration' => max(0, $history->last_login->diffInSeconds(now())),
        ]);
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }
}
