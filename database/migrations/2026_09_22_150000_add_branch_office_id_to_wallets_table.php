<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur Saldo Branch/Company/Reseller (Chat > diskusi 22 September 2026):
 * App\Models\Wallet SEBELUMNYA cuma bisa milik satu User (lihat
 * create_wallets_table.php). Sekarang Wallet juga bisa mewakili saldo
 * TERKUMPUL satu App\Models\BranchOffice (terisi dari App\Models\
 * TagihanPenerima yang lunas -- lihat App\Http\Controllers\Tagihan\
 * TagihanDuitkuCallbackController), yang nantinya dibagi ke Wallet
 * pengajar (fitur "Transfer Fee" bulanan) dan sisanya ditarik Company
 * ke rekening banknya sendiri (fitur "Tarik Saldo", lihat migration
 * create_wallet_withdrawals_table.php).
 *
 * `user_id` DIUBAH jadi nullable (sebelumnya NOT NULL) supaya baris
 * Wallet milik branch tidak perlu punya user_id sama sekali --
 * TIDAK pakai ->nullable()->change() (butuh doctrine/dbal, TIDAK
 * terpasang di composer.lock proyek ini) melainkan raw ALTER TABLE,
 * pola yang sudah dipakai migration lain di proyek ini yang mengubah
 * kolom existing tanpa dbal.
 *
 * Constraint "harus persis salah satu dari user_id/branch_office_id
 * yang terisi" SENGAJA tidak dipaksakan lewat CHECK constraint MySQL
 * (butuh MySQL 8.0.16+, tidak dijamin tersedia di semua environment
 * proyek ini) -- ditegakkan di level aplikasi oleh App\Services\
 * Wallet\WalletProvisioningService, sama seperti invariant LedgerEntry
 * immutable yang juga ditegakkan lewat model event, bukan DB trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wallets', 'branch_office_id')) {
            Schema::table('wallets', function ($table) {
                $table->foreignUuid('branch_office_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('branch_offices')
                    ->restrictOnDelete();
            });
        }

        // user_id: NOT NULL -> nullable. Raw ALTER karena tidak ada
        // doctrine/dbal terpasang (lihat docblock di atas). Guard lewat
        // information_schema supaya migration ini aman dijalankan ulang
        // (idempotent), konsisten dengan pola Schema::hasIndex() di
        // migration lain proyek ini.
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallets' AND COLUMN_NAME = 'user_id'"
        );

        if ($column && $column->IS_NULLABLE === 'NO') {
            // Drop dulu FK lama (restrictOnDelete) sebelum ALTER kolomnya
            // -- MySQL menolak mengubah nullability kolom yang masih
            // terikat foreign key kalau tidak begini. Nama constraint FK
            // default Laravel: wallets_user_id_foreign.
            DB::statement('ALTER TABLE wallets DROP FOREIGN KEY wallets_user_id_foreign');
            DB::statement('ALTER TABLE wallets MODIFY user_id CHAR(36) NULL');
            DB::statement(
                'ALTER TABLE wallets ADD CONSTRAINT wallets_user_id_foreign
                 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT'
            );
        }

        if (! Schema::hasIndex('wallets', 'wallets_branch_office_id_currency_unique')) {
            Schema::table('wallets', function ($table) {
                $table->unique(['branch_office_id', 'currency'], 'wallets_branch_office_id_currency_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('wallets', 'wallets_branch_office_id_currency_unique')) {
            Schema::table('wallets', function ($table) {
                $table->dropUnique('wallets_branch_office_id_currency_unique');
            });
        }

        if (Schema::hasColumn('wallets', 'branch_office_id')) {
            Schema::table('wallets', function ($table) {
                $table->dropConstrainedForeignId('branch_office_id');
            });
        }

        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallets' AND COLUMN_NAME = 'user_id'"
        );

        if ($column && $column->IS_NULLABLE === 'YES') {
            DB::statement('ALTER TABLE wallets DROP FOREIGN KEY wallets_user_id_foreign');
            DB::statement('ALTER TABLE wallets MODIFY user_id CHAR(36) NOT NULL');
            DB::statement(
                'ALTER TABLE wallets ADD CONSTRAINT wallets_user_id_foreign
                 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT'
            );
        }
    }
};
