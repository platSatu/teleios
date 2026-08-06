<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\HistoryUserLogin;
use App\Models\User;
use App\Rules\Turnstile;
use App\Services\Chat\SystemJwtService;
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
use Laravel\Socialite\Facades\Socialite;

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
 *   - redirectToGoogle()/handleGoogleCallback(): "Sign in/up with
 *     Google" via laravel/socialite. A Google-verified email skips the
 *     status='inactive'/verification-email step entirely — see
 *     handleGoogleCallback()'s own docblock for the account-matching
 *     rules, and finishLogin()'s docblock for how it still gets a
 *     working Go-backend JWT despite never having a real password.
 */
class AuthController extends Controller
{
    /**
     * Cap for login() — see ensureIsNotRateLimited()/throttleKey().
     */
    private const LOGIN_MAX_ATTEMPTS = 3;

    private const LOGIN_DECAY_SECONDS = 3600; // 60 minutes

    /**
     * Cap for register()/forgotPassword() — see ensureActionIsNotRateLimited().
     */
    private const EMAIL_ACTION_MAX_ATTEMPTS = 2;

    private const EMAIL_ACTION_DECAY_SECONDS = 900; // 15 minutes

    public function __construct(
        protected GolangAuthService $golangAuth,
        protected SystemJwtService $systemJwt,
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
            'cf-turnstile-response' => $this->turnstileRules(),
        ]);

        $this->ensureIsNotRateLimited($request);

        // Single parameterized lookup by email. Eloquent/query builder
        // binds every value through PDO prepared statements — user
        // input is never interpolated into the SQL string itself — so
        // this can't be manipulated into a different query no matter
        // what's submitted in the email field.
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request), self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Correct credentials but not an active account — a deliberately
        // different message from "invalid credentials" above, since the
        // person typing them already proved they know the password.
        if ($user->status !== 'active') {
            RateLimiter::hit($this->throttleKey($request), self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Akun Anda belum aktif. Silakan verifikasi email Anda terlebih dahulu sebelum login.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        return $this->finishLogin($request, $user, $validated['password']);
    }

    // =========================================================
    // GOOGLE OAUTH
    // =========================================================

    /**
     * GET /auth/google — kicks off the OAuth dance by bouncing the
     * browser to Google's own consent screen. stateless() since this app
     * has no long-lived "linked account" management UI yet — each click
     * is a fresh, independent login/register attempt, so there's nothing
     * gained from Socialite's default session-based state persistence
     * (and it avoids state-mismatch errors if the guest session doesn't
     * carry a cookie yet on a first-ever visit).
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * GET /auth/google/callback — Google redirects here with the user's
     * profile after they approve the consent screen. Three cases:
     *
     *   1. google_id already on file -> that's our user, log them in.
     *   2. No google_id, but the email matches an existing account ->
     *      link this Google identity to it (someone who originally
     *      registered with a password is now also using "Sign in with
     *      Google") and log them in.
     *   3. Neither -> brand new account. Google already verified this
     *      email address by letting the user complete its own consent
     *      screen, so — unlike register() — this account is created
     *      status=active/email_verified_at=now() immediately, no
     *      separate verification email needed. The password column is
     *      NOT nullable (see 0001_01_01_000000_create_users_table.php),
     *      so a random one is stored — it's unusable for a normal
     *      password login until the user sets a real one via "forgot
     *      password".
     *
     * finishLogin() below still gets a working golang_jwt_token for this
     * session even though this account's real password is never known
     * to the browser Google redirected back — see its own docblock for
     * how (SystemJwtService, not Go's password-checking /api/auth/login).
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['email' => 'Login dengan Google gagal atau dibatalkan. Silakan coba lagi.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            if ($user->status !== 'active') {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Akun Anda belum aktif. Silakan verifikasi email Anda terlebih dahulu sebelum login.']);
            }
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(40)),
                'status' => 'active',
            ]);

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ])->save();
        }

        return $this->finishLogin($request, $user);
    }

    /**
     * Shared tail end of a successful authentication, regardless of
     * whether it came from login() (email+password, $plainPassword
     * known) or handleGoogleCallback() ($plainPassword null — Google
     * never gives this app a plaintext password to verify against Go's
     * own /api/auth/login).
     *
     * Either way, session('golang_jwt_token') ends up populated so
     * WhatsApp-device features (Connect Device, Inbox, the header
     * notification bell, ...) work identically regardless of which way
     * someone signed in — this used to be skipped entirely for Google
     * sessions (see git history), which is exactly why "Add Device"
     * silently didn't work for anyone who only ever used "Sign in with
     * Google": every Chat controller hard-requires this session key
     * (e.g. Chat\ConnectDeviceController::index()).
     */
    private function finishLogin(Request $request, User $user, ?string $plainPassword = null): RedirectResponse
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Treated as non-fatal either way: if the Go backend is
        // unreachable, the web app keeps working and only WhatsApp
        // features are unavailable until the next successful login.
        try {
            if ($plainPassword !== null) {
                // Real credentials known — go through Go's own
                // /api/auth/login so it's Go itself vouching for the
                // password, not just Laravel's own DB check.
                $token = $this->golangAuth->login($user->email, $plainPassword);
            } else {
                // No plaintext password exists for this session (Google
                // OAuth) — mint a system JWT locally instead. Go can't
                // tell the difference (see SystemJwtService's docblock);
                // this is exactly the same trick SendScheduledWaMessage
                // already uses for a user with nobody logged in at all,
                // just with a session-length TTL instead of a 5-minute
                // one since a person is actually going to sit in the
                // browser using it.
                $token = $this->systemJwt->mintFor($user, SystemJwtService::TTL_SESSION_SECONDS);
            }

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
            'cf-turnstile-response' => $this->turnstileRules(),
        ]);

        // Max 2 registrations per email+IP per 15 minutes — an active
        // user not receiving the verification email is almost always a
        // deliverability issue, not something retrying a 3rd/4th time
        // fixes, so this is really an anti-spam cap rather than a UX
        // limitation. See ensureActionIsNotRateLimited().
        $this->ensureActionIsNotRateLimited($request, 'register');
        RateLimiter::hit($this->actionThrottleKey($request, 'register'), self::EMAIL_ACTION_DECAY_SECONDS);

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
        $request->validate([
            'email' => ['required', 'email'],
            'cf-turnstile-response' => $this->turnstileRules(),
        ]);

        // Only 'email' — deliberately NOT the full validated array, since
        // it also contains cf-turnstile-response and Password::
        // sendResetLink() below uses whatever's passed in here as a raw
        // WHERE clause against the users table (there's no
        // `cf-turnstile-response` column, so that would blow up).
        $validated = $request->only('email');

        // Max 2 reset-link requests per email+IP per 15 minutes — same
        // reasoning as register(): a legitimate user not getting the
        // email after 2 tries has a deliverability problem, not one more
        // click will fix, so further requests are almost certainly spam.
        $this->ensureActionIsNotRateLimited($request, 'forgot-password');
        RateLimiter::hit($this->actionThrottleKey($request, 'forgot-password'), self::EMAIL_ACTION_DECAY_SECONDS);

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
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::LOGIN_MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $minutes = (int) ceil(RateLimiter::availableIn($this->throttleKey($request)) / 60);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login yang salah. Silakan coba kembali dalam {$minutes} menit ke depan.",
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }

    /**
     * Same email+IP-keyed pattern as ensureIsNotRateLimited()/throttleKey()
     * above (login), reused for register()/forgotPassword() — capped at
     * EMAIL_ACTION_MAX_ATTEMPTS per EMAIL_ACTION_DECAY_SECONDS. $action
     * namespaces the key so a register() lockout and a forgotPassword()
     * lockout for the same email+IP don't share (or reset) each other's
     * counter.
     */
    private function ensureActionIsNotRateLimited(Request $request, string $action): void
    {
        $key = $this->actionThrottleKey($request, $action);

        if (! RateLimiter::tooManyAttempts($key, self::EMAIL_ACTION_MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan. Silakan coba kembali dalam {$minutes} menit ke depan.",
        ]);
    }

    private function actionThrottleKey(Request $request, string $action): string
    {
        return $action.'|'.$this->throttleKey($request);
    }

    /**
     * Validation rules for the `cf-turnstile-response` field on login/
     * register/forgotPassword. Only actually required (and verified,
     * see App\Rules\Turnstile) when this deployment has a Turnstile site
     * key configured — otherwise 'nullable' so a fresh clone / the test
     * suite (neither of which sends a real widget token) isn't
     * permanently locked out of every auth form.
     */
    private function turnstileRules(): array
    {
        return config('services.turnstile.site_key')
            ? ['required', new Turnstile()]
            : ['nullable'];
    }
}
