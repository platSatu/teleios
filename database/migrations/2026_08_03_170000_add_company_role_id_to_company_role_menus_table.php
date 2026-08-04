<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Makes CompanyRoleMenu actually per-role, matching its name — until
     * now it only had company_id + application_menu_id, so switching a
     * menu "on" applied to every member of the company regardless of
     * their CompanyRole. See App\Services\Company\CompanyContextResolver
     * and the "Applications" tab (User\Profile\CompanyRoleMenuController)
     * for how this is used once populated: the owner picks a CompanyRole,
     * then checks which ApplicationMenu entries that role can see.
     *
     * Nullable at the column level (not every historical row can be
     * confidently mapped to "the" role), but every row that exists today
     * gets backfilled to its company's "Owner" role below — that's the
     * only role guaranteed to exist for every company (auto-seeded in
     * User\Profile\ProfileController::updateCompany()), and it's the
     * safest default: the owner keeps seeing every menu they'd already
     * switched on, nothing silently disappears. Other roles start with
     * zero menus checked until the owner explicitly grants them.
     *
     * Every step below is guarded (hasColumn/index existence checks)
     * instead of running unconditionally — MySQL's DDL statements each
     * auto-commit on their own (no transactional rollback across a
     * failed migration), so if this migration ever fails partway
     * through, simply re-running `php artisan migrate` must be able to
     * pick up from wherever it actually stopped rather than erroring on
     * "column already exists" the second time.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('company_role_menus', 'company_role_id')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->foreignUuid('company_role_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('company_roles')
                    ->cascadeOnDelete();
            });
        }

        $this->backfillToOwnerRole();

        // MySQL requires SOME index whose leftmost column is company_id
        // to satisfy company_id's own foreign key — the composite unique
        // index we're about to drop below happens to be the only thing
        // currently providing that (its leftmost column is company_id),
        // so dropping it outright fails with error 1553 ("Cannot drop
        // index ... needed in a foreign key constraint"). Adding this
        // plain, non-unique index FIRST gives MySQL an alternative, so
        // the drop below succeeds. It stays in place afterward —
        // redundant once the new composite unique (also company_id-led)
        // exists too, but harmless.
        if (! $this->indexExists('company_role_menus', 'company_role_menus_company_id_index')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->index('company_id', 'company_role_menus_company_id_index');
            });
        }

        if ($this->indexExists('company_role_menus', 'company_role_menus_company_id_application_menu_id_unique')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->dropUnique('company_role_menus_company_id_application_menu_id_unique');
            });
        }

        if (! $this->indexExists('company_role_menus', 'company_role_menus_role_menu_unique')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->unique(['company_id', 'company_role_id', 'application_menu_id'], 'company_role_menus_role_menu_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('company_role_menus', 'company_role_menus_role_menu_unique')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->dropUnique('company_role_menus_role_menu_unique');
            });
        }

        if (Schema::hasColumn('company_role_menus', 'company_role_id')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_role_id');
            });
        }

        if (! $this->indexExists('company_role_menus', 'company_role_menus_company_id_application_menu_id_unique')) {
            Schema::table('company_role_menus', function (Blueprint $table) {
                $table->unique(['company_id', 'application_menu_id']);
            });
        }
    }

    /**
     * One UPDATE per company (not per row) — cheap even with a lot of
     * existing data, and keeps the logic readable instead of a raw
     * multi-table UPDATE...JOIN. Safe to re-run: only ever touches rows
     * that still have a NULL company_role_id.
     */
    private function backfillToOwnerRole(): void
    {
        $companyIds = DB::table('company_role_menus')
            ->whereNull('company_role_id')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $ownerRoleId = DB::table('company_roles')
                ->where('company_id', $companyId)
                ->where('name', 'Owner')
                ->value('id');

            // Extremely defensive fallback — every company should have an
            // "Owner" role by construction, but if one somehow doesn't,
            // create it rather than leaving these rows permanently
            // role-less (which the new unique index would still allow,
            // since NULL isn't equal to NULL, but is confusing to reason
            // about later).
            if (! $ownerRoleId) {
                $ownerRoleId = (string) Str::uuid();

                DB::table('company_roles')->insert([
                    'id' => $ownerRoleId,
                    'company_id' => $companyId,
                    'name' => 'Owner',
                    'description' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('company_role_menus')
                ->where('company_id', $companyId)
                ->whereNull('company_role_id')
                ->update(['company_role_id' => $ownerRoleId]);
        }
    }

    /**
     * No portable `Schema::hasIndex()` exists on all Laravel versions,
     * and the index NAME (not just the columns) matters here since we
     * check for specific ones by name — SHOW INDEX is the direct way to
     * ask MySQL "does an index with this exact name exist right now".
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix() . $table;

        $result = $connection->select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }
};
