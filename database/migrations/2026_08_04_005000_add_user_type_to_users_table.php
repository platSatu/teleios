<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restores the `user_type` column on `users` — the migration that
     * originally added it is missing from database/migrations (the
     * codebase references `user_type` everywhere: App\Models\User's
     * $fillable, App\Http\Middleware\SuperadminMiddleware,
     * App\Providers\AppServiceProvider, Superadmin\UserController, etc.
     * — but no migration in this repo actually creates the column, so a
     * fresh `php artisan migrate` fails the moment anything tries to
     * write to it).
     *
     * Defaults to 'USER' to match every self-registration path
     * (App\Http\Controllers\Auth\AuthController::register() never sets
     * user_type explicitly — it relies entirely on the column default),
     * and existing rows created before this migration ran are backfilled
     * to 'USER' too so no one is silently promoted to SUPERADMIN by a
     * NULL default.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'user_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['USER', 'SUPERADMIN'])
                ->default('USER')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
