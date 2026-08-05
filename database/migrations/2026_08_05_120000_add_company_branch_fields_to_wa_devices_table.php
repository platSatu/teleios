<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `wa_devices` itself is owned/created by the Go backend (g_backend's
     * WaConnectDeviceService, via GORM AutoMigrate on startup — see that
     * repo's cmd/server/main.go) — it lives in this same "teleios"
     * database, but Laravel has no Eloquent model or migration for the
     * table as a whole. This migration only ADDS the two columns that
     * make WhatsApp device visibility follow the same
     * owner-sees-everything / branch-locked-member-sees-their-branch
     * rule already enforced everywhere else in the app (see
     * App\Services\Company\CompanyContextResolver and
     * User\Profile\CompanyUserController).
     *
     * Written as an explicit Laravel migration (run via the normal
     * `php artisan migrate` deploy step) rather than left solely to the
     * Go side's AutoMigrate, so the schema change ships through the same
     * reviewed, source-controlled path as every other table here instead
     * of depending on remembering to restart the Go service. Column
     * types/names match exactly what g_backend's models.WaDevice
     * (CompanyID/BranchOfficeID, `type:char(36)`) expects — GORM's
     * AutoMigrate is non-destructive and simply no-ops on a column that
     * already exists, so having both isn't a conflict, just redundant
     * safety.
     *
     * Both nullable: a device added by a standalone user with no
     * Company/CompanyToUser row at all has NULL for both (falls back to
     * the original per-user_id visibility rule in ListDevices).
     *
     * NO real foreign key constraint — deliberately. This kept failing
     * with errno 150 ("foreign key constraint is incorrectly formed")
     * across two different fix attempts (column type, then explicit
     * charset/collation), which points at a STORAGE ENGINE mismatch
     * between `wa_devices` (created by Go/GORM's AutoMigrate — possibly
     * MyISAM depending on the MySQL/MariaDB server's default) and
     * `companies`/`branch_offices` (created by Laravel's own migrations,
     * InnoDB) — MySQL cannot form a foreign key across two tables using
     * different storage engines, full stop, no column-level fix
     * resolves that. Rather than depend on ALTER-ing wa_devices' engine
     * (a destructive-ish operation on a table this app doesn't own/
     * control the lifecycle of, and GORM's AutoMigrate could plausibly
     * recreate it differently later anyway), this just adds a plain
     * indexed nullable column with NO DB-level referential enforcement.
     * Every write to these two columns already only ever comes from
     * controlled application code (Go's resolveCompanyContext(), this
     * app's own controllers) — never raw user input — so the integrity
     * guarantee lives at the application layer instead, same tradeoff
     * every other Laravel<->Go shared-table interaction in this app
     * already makes (there's no Eloquent model for wa_devices at all).
     */
    public function up(): void
    {
        // Guard, not an assumption failure: `wa_devices` only exists once
        // the Go backend has started at least once and run its own
        // AutoMigrate (see class docblock — Laravel never creates this
        // table itself). On an environment where Go hasn't run yet, this
        // quietly no-ops instead of hard-failing the whole
        // `php artisan migrate` batch — but note Laravel still marks
        // THIS migration as run either way, so on such an environment
        // the columns won't retroactively appear on their own; whoever
        // sets it up would need `php artisan migrate:rollback --step=1`
        // then `migrate` again after Go's first startup has created the
        // table. In this app's actual deployment, `wa_devices` has
        // existed for a while already, so this branch is dead code in
        // practice — it only guards a hypothetical fresh-install
        // ordering issue.
        if (! Schema::hasTable('wa_devices')) {
            return;
        }

        $this->addIndexedColumnIfMissing('company_id', 'user_id');
        $this->addIndexIfMissing('company_id');

        $this->addIndexedColumnIfMissing('branch_office_id', 'company_id');
        $this->addIndexIfMissing('branch_office_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_devices')) {
            return;
        }

        $this->dropColumnIfExists('branch_office_id');
        $this->dropColumnIfExists('company_id');
    }

    /**
     * Plain nullable uuid column only — see this class's docblock for
     * why there's no ->constrained()/->foreign() here. The index is
     * added separately (see addIndexIfMissing()) so a column left over
     * from the earlier, now-abandoned FK attempt (created, but without
     * an index since that attempt failed before getting that far) still
     * gets one when this migration is re-run, instead of being skipped
     * just because the column itself already exists.
     */
    private function addIndexedColumnIfMissing(string $column, string $after): void
    {
        if (Schema::hasColumn('wa_devices', $column)) {
            return;
        }

        Schema::table('wa_devices', function (Blueprint $table) use ($column, $after) {
            $table->uuid($column)->nullable()->after($after);
        });
    }

    private function addIndexIfMissing(string $column): void
    {
        if ($this->indexExists($column)) {
            return;
        }

        Schema::table('wa_devices', function (Blueprint $table) use ($column) {
            $table->index($column);
        });
    }

    private function indexExists(string $column): bool
    {
        $connection = Schema::getConnection();

        $rows = $connection->select(
            'SELECT COUNT(*) AS cnt
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$connection->getDatabaseName(), 'wa_devices', $column]
        );

        return (int) ($rows[0]->cnt ?? 0) > 0;
    }

    private function dropColumnIfExists(string $column): void
    {
        if (! Schema::hasColumn('wa_devices', $column)) {
            return;
        }

        Schema::table('wa_devices', function (Blueprint $table) use ($column) {
            // dropColumn() also drops any plain index defined on it in
            // the same call — no separate dropIndex() needed here since
            // there's no foreign key to drop first (see up()).
            $table->dropColumn($column);
        });
    }
};
