<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional branch scoping for App\Models\CompanyRole — "1 cabang punya
 * role dan divisi masing-masing" (per the Jadwal Kelas feature spec).
 * Nullable and purely additive: every existing role keeps
 * branch_office_id = null, which means exactly what it always has —
 * applies across the whole company. Only newly created roles meant to
 * be branch-specific (e.g. a "Guru" role that only makes sense scoped
 * to one cabang's own subjects/schedules) set this.
 *
 * Real FK here (unlike wa_devices.branch_office_id) since both
 * company_roles and branch_offices are Laravel-owned tables on the same
 * storage engine — no cross-engine FK issue to work around.
 * nullOnDelete(): deleting a branch shouldn't cascade-delete a role
 * (and any CompanyToUser rows still pointing at it) — it just reverts
 * to being a company-wide role instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->after('company_id')
                ->constrained('branch_offices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_office_id');
        });
    }
};
