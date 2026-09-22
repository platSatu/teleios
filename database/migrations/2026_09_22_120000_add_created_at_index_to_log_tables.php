<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLAUDE.md checklist item #6 — the 5 log/history tables below are all
 * queried the same way in practice: `Model::where(<filter column>, $id)
 * ->latest()->paginate()` (see Superadmin\UserController,
 * Superadmin\VoucherController, Superadmin\DepositController,
 * User\History\HistoryUserController, Auth\AuthController). Each already
 * has SOME index touching its filter column (either an explicit
 * ->index()/->constrained() FK index, or nothing at all for audit_logs'
 * created_at), but none of them let the database seek straight to a date
 * range within one filter value — same gap `wa_device_histories` had
 * before 2026_08_12_170000_add_reporting_index_to_wa_device_histories_table.php,
 * and the same fix: a composite (filter_column, created_at) index per
 * table, added additively so nothing existing is touched or dropped.
 *
 * Deliberately one migration for all 5 tables rather than 5 files — this
 * is a single checklist item (#6), not 5 separate structural changes, and
 * the pattern is identical for each: guard table existence, guard index
 * existence via Schema::hasIndex() (Laravel 12 built-in — NOT a raw
 * information_schema query and NOT try/catch inside Schema::table(), per
 * the jadwal_pengajar_kategori lesson already documented elsewhere in
 * CLAUDE.md: Blueprint commands only run after the closure returns, so a
 * try/catch around them never actually guards anything), then add.
 *
 * payment_transactions is the one exception worth calling out: it already
 * has a composite (reference_type, reference_id) index, just without
 * created_at. Rather than drop/redefine that existing index (risking any
 * other query plan relying on it), this adds a second, wider composite
 * (reference_type, reference_id, created_at) index alongside it — a
 * small amount of index redundancy, traded for zero risk to whatever
 * already depends on the original index shape.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $indexes = [
        'audit_logs' => ['actor_id', 'created_at'],
        'ledger_entries' => ['user_id', 'created_at'],
        'history_user_login' => ['user_id', 'created_at'],
        'voucher_histories' => ['voucher_id', 'created_at'],
        'payment_transactions' => ['reference_type', 'reference_id', 'created_at'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexName = $this->indexName($table, $columns);

            if (Schema::hasIndex($table, $indexName)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexName = $this->indexName($table, $columns);

            if (! Schema::hasIndex($table, $indexName)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }

    /**
     * Spells out Laravel's own default index-naming convention
     * (`<table>_<col1>_<col2>..._index`) explicitly, rather than relying
     * on Blueprint to generate it implicitly, so up() and down() are
     * guaranteed to agree on the same name Schema::hasIndex() checks.
     *
     * @param  array<int, string>  $columns
     */
    private function indexName(string $table, array $columns): string
    {
        return strtolower($table.'_'.implode('_', $columns).'_index');
    }
};
