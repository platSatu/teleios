<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Companion wajib untuk 2026_09_22_150000_add_branch_office_id_to_wallets_table.php
 * -- App\Services\Wallet\WalletLedgerService::move() menyalin
 * $wallet->user_id apa adanya ke LedgerEntry.user_id (lihat
 * create_ledger_entries_table.php, kolomnya masih NOT NULL). Begitu
 * ada Wallet milik BranchOffice (user_id = null), setiap
 * WalletLedgerService::credit()/debit() ke wallet itu akan gagal
 * dengan SQL error "Column 'user_id' cannot be null" kalau kolom ini
 * TIDAK ikut diubah nullable -- migration ini menutup celah itu SEBELUM
 * App\Http\Controllers\Tagihan\TagihanDuitkuCallbackController mulai
 * meng-kredit Wallet Branch.
 *
 * Raw ALTER (bukan ->nullable()->change()) dengan alasan yang sama
 * persis seperti migration wallets di atas: doctrine/dbal tidak
 * terpasang di composer.lock proyek ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_entries' AND COLUMN_NAME = 'user_id'"
        );

        if ($column && $column->IS_NULLABLE === 'NO') {
            DB::statement('ALTER TABLE ledger_entries DROP FOREIGN KEY ledger_entries_user_id_foreign');
            DB::statement('ALTER TABLE ledger_entries MODIFY user_id CHAR(36) NULL');
            DB::statement(
                'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_user_id_foreign
                 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT'
            );
        }
    }

    public function down(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_entries' AND COLUMN_NAME = 'user_id'"
        );

        if ($column && $column->IS_NULLABLE === 'YES') {
            DB::statement('ALTER TABLE ledger_entries DROP FOREIGN KEY ledger_entries_user_id_foreign');
            DB::statement('ALTER TABLE ledger_entries MODIFY user_id CHAR(36) NOT NULL');
            DB::statement(
                'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_user_id_foreign
                 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT'
            );
        }
    }
};
