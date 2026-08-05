<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Http\Controllers\Auth\AuthController::handleGoogleCallback().
 * Nullable + unique: most users still register the normal
 * email/password way and never get a google_id, but once a user does
 * sign in via Google we never want two different Laravel accounts
 * mapped to the same Google account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
