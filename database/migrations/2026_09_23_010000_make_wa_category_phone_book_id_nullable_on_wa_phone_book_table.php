<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Companion for InboxController's new "Simpan ke Buku Telepon" quick-add
 * flow (Inbox detail panel, 23 September 2026 request: "ditanya dulu mau
 * disimpan ke grup yang mana nullable tapi terus lnsng save meskipun
 * hanya no handphone dan nama") -- the person wants Kelompok to be
 * optional there, but wa_phone_book.wa_category_phone_book_id was NOT
 * NULL with a cascadeOnDelete FK (see create_wa_phone_book_table.php),
 * so a save with no Kelompok chosen would fail outright with a SQL
 * constraint error before this migration.
 *
 * Raw ALTER (not ->nullable()->change()) for the same reason as
 * 2026_09_22_150300_make_user_id_nullable_on_ledger_entries_table.php:
 * doctrine/dbal isn't installed in this project's composer.lock.
 *
 * cascadeOnDelete -> nullOnDelete on the way: now that a row can exist
 * with no Kelompok, deleting a Kelompok that still has contacts filed
 * under it should un-file them (null out the column), not silently mass
 * -delete every contact that happened to be in it -- cascadeOnDelete was
 * only ever safe because every row was guaranteed to have a Kelompok.
 */
return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_phone_book' AND COLUMN_NAME = 'wa_category_phone_book_id'"
        );

        if ($column && $column->IS_NULLABLE === 'NO') {
            DB::statement('ALTER TABLE wa_phone_book DROP FOREIGN KEY wa_phone_book_wa_category_phone_book_id_foreign');
            DB::statement('ALTER TABLE wa_phone_book MODIFY wa_category_phone_book_id CHAR(36) NULL');
            DB::statement(
                'ALTER TABLE wa_phone_book ADD CONSTRAINT wa_phone_book_wa_category_phone_book_id_foreign
                 FOREIGN KEY (wa_category_phone_book_id) REFERENCES wa_category_phone_book(id) ON DELETE SET NULL'
            );
        }
    }

    public function down(): void
    {
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_phone_book' AND COLUMN_NAME = 'wa_category_phone_book_id'"
        );

        if ($column && $column->IS_NULLABLE === 'YES') {
            // Existing NULL rows would violate the NOT NULL we're about
            // to restore -- there's no sane default Kelompok to backfill
            // them with, so this rollback only proceeds if none exist.
            $orphaned = DB::table('wa_phone_book')->whereNull('wa_category_phone_book_id')->count();

            if ($orphaned === 0) {
                DB::statement('ALTER TABLE wa_phone_book DROP FOREIGN KEY wa_phone_book_wa_category_phone_book_id_foreign');
                DB::statement('ALTER TABLE wa_phone_book MODIFY wa_category_phone_book_id CHAR(36) NOT NULL');
                DB::statement(
                    'ALTER TABLE wa_phone_book ADD CONSTRAINT wa_phone_book_wa_category_phone_book_id_foreign
                     FOREIGN KEY (wa_category_phone_book_id) REFERENCES wa_category_phone_book(id) ON DELETE CASCADE'
                );
            }
        }
    }
};
