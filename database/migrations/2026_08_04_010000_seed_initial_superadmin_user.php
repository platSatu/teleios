<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Guarantees the very first superadmin account exists right after a
     * fresh `php artisan migrate` on a new install (e.g. the first
     * deploy to a VPS) — without this there'd be no way to log in at
     * all, since users only ever self-register as regular USER accounts
     * (see Auth\RegisteredUserController) and promoting someone to
     * SUPERADMIN already requires an existing superadmin to do it via
     * Superadmin\UserController::update().
     *
     * Goes through the User model rather than a raw DB::table() insert
     * (unlike this project's other seed_* migrations) specifically so
     * User::boot()'s `created` listener fires and creates the matching
     * Wallet + ReferralCode rows every other user gets, and so the
     * password goes through User::casts()'s 'password' => 'hashed' cast
     * instead of being hashed by hand here.
     *
     * Idempotent: matched by email, so re-running this (or migrating a
     * database that already has this account — e.g. a seeded dev copy)
     * never creates a duplicate account or a second wallet/referral
     * code.
     */
    public function up(): void
    {
        if (User::where('email', 'natanaeltamto@gmail.com')->exists()) {
            return;
        }

        $user = User::create([
            'name' => 'Natanael Tamto',
            'email' => 'natanaeltamto@gmail.com',
            'password' => 'Bogor123',
            'status' => 'active',
            'user_type' => 'SUPERADMIN',
        ]);

        // Admin/seed-created accounts skip email verification the same
        // way Superadmin\UserController::store() does for any account a
        // superadmin adds directly — there's no signup flow here for
        // anyone to click a verification link from. Direct property
        // assignment (not ->update()) because email_verified_at isn't
        // in User::$fillable.
        $user->email_verified_at = now();
        $user->save();
    }

    public function down(): void
    {
        // Intentionally a no-op. By the time this runs, this account is
        // very likely the one being used to operate the app (possibly
        // even to run `php artisan migrate:rollback` itself), and it's
        // likely to have real wallet/deposit/ledger history attached —
        // all restrictOnDelete (see App\Models\Wallet, LedgerEntry) —
        // which would make a straight delete throw anyway. Rolling back
        // is for undoing schema changes, not for deciding whether to
        // remove a login that may now be load-bearing.
    }
};
