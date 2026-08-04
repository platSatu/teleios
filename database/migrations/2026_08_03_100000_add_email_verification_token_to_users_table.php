<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the custom (non-Laravel-signed-URL) email verification flow in
 * App\Http\Controllers\Auth\AuthController — a fresh registration starts
 * status='inactive' (already the column default) and can't log in until
 * this token is clicked. Laravel's stock MustVerifyEmail flow relies on
 * a signed URL + an authenticated "please verify" prompt, which doesn't
 * fit here: an inactive user can't log in at all, so there's no
 * authenticated session to show that prompt to. A stored, expiring token
 * lets both the initial link AND a later resend be validated the same
 * way, purely as a guest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_token', 64)->nullable()->unique()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_token', 'email_verification_expires_at']);
        });
    }
};
