<?php

use App\Models\DuitkuDisbursementSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\DuitkuDisbursementSetting -- kredensial Duitku
 * Disbursement (fitur "Tarik Saldo" ke rekening bank, didiskusikan 22
 * September 2026), SENGAJA tabel terpisah dari duitku_settings
 * (App\Models\DuitkuSetting) yang sudah ada. Dua produk Duitku yang
 * berbeda dengan cara autentikasi berbeda:
 *   - duitku_settings   -> merchantCode + apiKey, HMAC-SHA256 (dipakai
 *     App\Services\Payment\DuitkuService untuk terima pembayaran).
 *   - duitku_disbursement_settings (tabel ini) -> userId + email +
 *     secretKey, SHA256 biasa (dipakai App\Services\Payment\
 *     DuitkuDisbursementService untuk KIRIM uang keluar).
 * Menyamakan pola singleton yang sama (current(), sandbox/production
 * sepasang-sepasang) supaya konsisten dengan DuitkuSetting.
 *
 * `secretKey` di-encrypt sama seperti *_api_key di duitku_settings --
 * tidak pernah disimpan/di-log polos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duitku_disbursement_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('mode', 20)->default(DuitkuDisbursementSetting::MODE_SANDBOX);

            $table->string('sandbox_user_id')->nullable();
            $table->string('sandbox_email')->nullable();
            $table->text('sandbox_secret_key')->nullable();

            $table->string('production_user_id')->nullable();
            $table->string('production_email')->nullable();
            $table->text('production_secret_key')->nullable();

            $table->foreignUuid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duitku_disbursement_settings');
    }
};
