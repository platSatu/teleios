<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Models\Wallet;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'handphone',
        'image',
       // 'role',
       // 'parent_id',
       // 'application_id',
        'status',
       // 'saldo',
       // 'image',
       'user_type',
       // Who referred this user — set once, the first time they enter a
       // valid referral code at checkout. See Dashboard\
       // PackageCheckoutController::store().
       'referrer_id',
       // Hashed 6-digit transaction PIN — only ever written by
       // User\Settings\PinController, never accepted directly from an
       // arbitrary form. Required before Dashboard\WalletTransferController
       // will let this user send a transfer.
       'pin',
    ];

    protected static function booted(): void
    {
        static::creating(function ($user) {
            $user->id = (string) Str::uuid();
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pin',
        'email_verification_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password' => 'hashed',
            // Same 'hashed' cast as password — assigning a plain 6-digit
            // string to ->pin automatically runs it through Hash::make()
            // on save, so User\Settings\PinController never has to
            // remember to do it manually.
            'pin' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();


        static::created(function ($user) {


            Wallet::create([

                'user_id' => $user->id,

                'currency' => 'IDR',

                'balance' => 0,

                'status' => 'ACTIVE',

            ]);


            // Unique referral code, auto-generated the moment a user
            // registers (same 1:1-with-user pattern as Wallet above).
            // Default commission 20%, editable/blockable later by
            // superadmin via Superadmin\ReferralCodeController.
            ReferralCode::create([

                'user_id' => $user->id,

                'code' => ReferralCode::generateUniqueCode($user->name),

                'percentage' => 20.00,

                'status' => 'active',

            ]);


        });

    }

    public function wallet()
    {
        return $this->hasOne(
            Wallet::class,
            'user_id',
            'id'
        );
    }

    /**
     * Overrides Illuminate\Auth\Passwords\CanResetPassword's default so
     * the forgot-password email is queued (App\Notifications\
     * ResetPasswordNotification implements ShouldQueue) instead of sent
     * inline during the request — see App\Http\Controllers\Auth\
     * AuthController::forgotPassword().
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    /**
     * Generates a fresh random token + expiry for the custom email
     * verification flow, persists it, and returns the token — called
     * both right after registration and every time
     * AuthController::verifyEmail()/resendVerification() needs to issue
     * a new link (an expired-token click auto-resends using this same
     * method, see AuthController::verifyEmail()).
     *
     * forceFill + save (not ->update()) since these two columns are
     * deliberately NOT in $fillable — nothing should ever be able to set
     * them via a mass-assigned request payload.
     */
    public function generateEmailVerificationToken(int $expiresInMinutes = 60): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addMinutes($expiresInMinutes),
        ])->save();

        return $token;
    }

    /**
     * Generates a token (see above) and emails it via the queued
     * VerifyEmailNotification. $expiresInMinutes is passed through to
     * the notification purely so the mail text can state how long the
     * link is valid for.
     */
    public function sendCustomVerificationEmail(int $expiresInMinutes = 60): void
    {
        $token = $this->generateEmailVerificationToken($expiresInMinutes);

        $this->notify(new \App\Notifications\VerifyEmailNotification($token, $expiresInMinutes));
    }

    /**
     * Uploaded avatar URL, or the template's default placeholder if this
     * user hasn't set one (or it's a demo/seeded user without a photo).
     * Centralized here so header/profile views don't repeat the fallback.
     */
    public function avatarUrl(): string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : asset('be') . '/assets/images/avatar/avatar-16.jpg';
    }

    public function referralCode()
    {
        return $this->hasOne(
            ReferralCode::class,
            'user_id',
            'id'
        );
    }

    /**
     * The user whose referral code THIS user entered (once, permanently
     * — see referrer_id migration/comment). Null if they never used one.
     */
    public function referrer()
    {
        return $this->belongsTo(
            User::class,
            'referrer_id'
        );
    }

    /**
     * Users who signed up/first purchased using THIS user's referral
     * code — the other side of referrer().
     */
    public function referredUsers()
    {
        return $this->hasMany(
            User::class,
            'referrer_id'
        );
    }

    public function deposits()
    {
        return $this->hasMany(
            Deposit::class,
            'user_id',
            'id'
        );
    }

    public function loginHistories()
    {
        return $this->hasMany(
            HistoryUserLogin::class,
            'user_id',
            'id'
        );
    }

    public function tenants()
    {
        return $this->hasMany(
            Tenant::class,
            'owner_id',
            'id'
        );
    }

    public function vouchers()
    {
        return $this->hasMany(
            Voucher::class,
            'user_id',
            'id'
        );
    }

    public function subscriptions()
    {
        return $this->hasMany(
            Subscription::class,
            'user_id',
            'id'
        );
    }

    /**
     * Companies this user owns (Company::user_id === $this->id) — see
     * User\Profile\ProfileController's "Company" tab, where a company is
     * created lazily the first time a user fills in that tab.
     */
    public function companies()
    {
        return $this->hasMany(
            Company::class,
            'user_id',
            'id'
        );
    }

    /**
     * Every company this user belongs to (as owner or as an invited
     * member), one row per company via the company_to_users pivot. See
     * App\Models\CompanyToUser::role() for which CompanyRole each
     * membership carries.
     */
    public function companyMemberships()
    {
        return $this->hasMany(
            CompanyToUser::class,
            'user_id',
            'id'
        );
    }

    public function sentTransfers()
    {
        return $this->hasMany(
            WalletTransfer::class,
            'sender_user_id'
        );
    }

    public function receivedTransfers()
    {
        return $this->hasMany(
            WalletTransfer::class,
            'receiver_user_id'
        );
    }
}
