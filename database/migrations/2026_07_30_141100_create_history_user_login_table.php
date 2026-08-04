<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\HistoryUserLogin. Table name is singular
     * ('history_user_login') to match the model's explicit $table
     * property, not Laravel's usual plural convention.
     *
     * Already written to by App\Http\Controllers\Auth\
     * AuthenticatedSessionController (store() creates a row on login,
     * destroy() fills in last_logout/duration on logout) — that code
     * existed before this migration did, so login has likely been
     * silently failing to persist history until now.
     */
    public function up(): void
    {
        Schema::create('history_user_login', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_logout')->nullable();
            $table->unsignedBigInteger('duration')->nullable(); // seconds

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_user_login');
    }
};
