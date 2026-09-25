<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat setiap request pihak ketiga ke WA API (App\Models\WaApiKey) —
 * satu baris per request yang lolos otentikasi VerifyWaApiKey, apa pun
 * hasilnya (terkirim, diblokir paket/kuota, gagal kirim ke device).
 *
 * Sebelum tabel ini ada, jejak request API cuma tersimpan di
 * laravel.log (teks, tidak bisa difilter per company/API key dari
 * dashboard, dan hilang begitu log dirotasi). Kuota-nya sendiri sudah
 * dihitung ke broadcast_send lewat InboxService::guardPackageLimit(),
 * tapi tanpa tabel ini tidak ada cara memisahkan "berapa pesan yang
 * keluar lewat API" dari broadcast/auto-reply biasa.
 *
 * Isi pesan SENGAJA tidak disimpan (cuma panjangnya), konsisten dengan
 * aturan project untuk tidak menyimpan/melog data yang tidak perlu.
 *
 * Idempotent (Schema::hasTable guard) supaya aman di-retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_api_request_logs')) {
            return;
        }

        Schema::create('wa_api_request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nullable + nullOnDelete: baris WaApiKey praktis tidak pernah
            // dihapus (regenerate token/secret menimpa baris yang SAMA,
            // lihat WaApiKeyController), tapi kalau suatu saat dihapus,
            // riwayat pemakaiannya tetap tersimpan untuk audit.
            $table->foreignUuid('wa_api_key_id')
                ->nullable()
                ->constrained('wa_api_keys')
                ->nullOnDelete();

            $table->string('device_id', 36);

            // Endpoint yang dipanggil, mis. 'send-message' — disiapkan
            // untuk endpoint pengirim lain ke depannya.
            $table->string('endpoint', 50);

            $table->string('recipient', 100)->nullable();
            $table->unsignedInteger('message_length')->nullable();

            // sent | blocked_package | blocked_quota | failed
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status');

            $table->string('wa_message_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            // Halaman riwayat & endpoint /usage selalu query per API key
            // (atau per company) + urut/filter waktu — tabel ini tumbuh
            // cepat, jadi wajib ber-index (CLAUDE.md section 0 poin 3).
            $table->index(['wa_api_key_id', 'created_at'], 'wa_api_req_logs_key_created_idx');
            $table->index(['wa_api_key_id', 'status', 'created_at'], 'wa_api_req_logs_key_status_created_idx');
            $table->index(['company_id', 'created_at'], 'wa_api_req_logs_company_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_api_request_logs');
    }
};
