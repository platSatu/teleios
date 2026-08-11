<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widens company_to_users' uniqueness from (user_id, company_id,
     * category_application_id) to also include branch_office_id and
     * branch_office_unit_id.
     *
     * Without this, a user can only ever have ONE row per (company,
     * category) — which blocks the exact real-world case that drove this
     * change: one person holding several roles across DIFFERENT branches
     * or divisions of the same company at once (e.g. an owner who is also
     * "Kasir" at Branch A and "Guru" at Branch B), or several roles across
     * different divisions of the SAME branch (Admin, Finance, and
     * Marketing all held by the same person while the team is small).
     * Each of those is a distinct (branch, unit) pair, so they're now
     * distinct rows instead of colliding on the old key.
     *
     * MySQL treats each NULL in a unique index as distinct from every
     * other NULL — same behavior the category_application_id column
     * already relies on for its "null = unrestricted" rows (see
     * 2026_07_31_060000's docblock) — so this doesn't tighten anything
     * for rows that leave branch/unit null; it only loosens the rows that
     * actually need to differ by branch/unit.
     *
     * Same "add the new index before dropping the old one" order as
     * 2026_07_31_060000, for the same reason: the old composite unique is
     * currently the only index satisfying the leftmost-prefix requirement
     * for user_id's foreign key, so it can't be dropped until another
     * index starting with user_id exists to take over that job.
     *
     * Every step is guarded with an existence check so this migration is
     * safe to re-run after a partial failure (MySQL DDL auto-commits per
     * statement, it doesn't roll back as a unit).
     */
    public function up(): void
    {
        if (! $this->indexExists('company_to_users', 'company_to_users_scope_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'company_id', 'branch_office_id', 'branch_office_unit_id', 'category_application_id'],
                    'company_to_users_scope_unique'
                );
            });
        }

        if ($this->indexExists('company_to_users', 'company_to_users_user_company_category_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->dropUnique('company_to_users_user_company_category_unique');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists('company_to_users', 'company_to_users_user_company_category_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'company_id', 'category_application_id'],
                    'company_to_users_user_company_category_unique'
                );
            });
        }

        if ($this->indexExists('company_to_users', 'company_to_users_scope_unique')) {
            Schema::table('company_to_users', function (Blueprint $table) {
                $table->dropUnique('company_to_users_scope_unique');
            });
        }
    }

    /**
     * Same helper as 2026_07_31_060000_add_category_application_id_to_company_to_users_table
     * — no doctrine/dbal installed, so SHOW INDEX is used directly instead
     * of Schema::hasColumn()'s (column-only) equivalent.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        $result = $connection->select(
            'SHOW INDEX FROM ' . $connection->getTablePrefix() . $table . ' WHERE Key_name = ?',
            [$indexName]
        );

        return count($result) > 0;
    }
};
