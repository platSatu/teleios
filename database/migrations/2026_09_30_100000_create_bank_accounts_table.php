<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening pencairan user (lihat App\Services\Wallet\BankAccountService).
 *
 * Satu baris = satu rekening yang pernah didaftarkan. Baris TIDAK pernah
 * diubah isinya atau dihapus -- ganti rekening = baris baru, baris lama
 * diberi replaced_at. Jadi tabel ini sekaligus menjadi riwayat rekening.
 *
 * - account_number dienkripsi (cast 'encrypted', pakai APP_KEY).
 * - account_number_hash (HMAC) untuk mendeteksi satu rekening dipakai
 *   banyak akun tanpa perlu mendekripsi.
 * - account_name selalu nama dari bank (inquiry Duitku), bukan ketikan user.
 * - Rekening bisa dipakai tarik saldo kalau status approved,
 *   active_at <= sekarang, dan belum melewati replaced_at.
 *
 * users.verified_bank_name = nama patokan dari rekening pertama. Rekening
 * berikutnya dengan nama berbeda harus diperiksa superadmin.
 *
 * wallet_withdrawals.bank_account_id = rekening yang dipakai saat tarik
 * saldo pribadi (Tarik Saldo Branch tetap diisi manual, jadi nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            $table->text('account_number');
            $table->char('account_number_hash', 64)->index();
            $table->string('account_last4', 4);
            $table->string('account_name', 255);
            $table->boolean('name_matched')->default(true);

            $table->string('status', 20); // pending_review | approved | rejected
            $table->timestamp('active_at')->nullable();
            $table->timestamp('replaced_at')->nullable();

            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('verified_bank_name', 255)->nullable()->after('pin');
        });

        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')->nullable()->after('branch_office_id')
                ->constrained('bank_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verified_bank_name');
        });

        Schema::dropIfExists('bank_accounts');
    }
};
