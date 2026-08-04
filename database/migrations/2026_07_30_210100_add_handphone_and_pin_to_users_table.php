<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `handphone`: needed so Dashboard\WalletTransferController can look
     * a recipient up by phone number OR email (as requested), same as
     * how e-wallet apps find a contact. Nullable + unique — not every
     * user has to set one, but two users can't share the same number.
     *
     * `pin`: hashed (Hash::make(), same as password) 6-digit transaction
     * PIN, required before a user can send a wallet transfer. Null until
     * they set one via User\Settings\PinController.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handphone', 20)->nullable()->unique()->after('email');
            $table->string('pin')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handphone']);
            $table->dropColumn(['handphone', 'pin']);
        });
    }
};
