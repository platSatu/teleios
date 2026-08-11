<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Locks a CompanyRole to one Division (BranchOfficeUnit), alongside
     * the branch_office_id it already got in 2026_08_07_110000.
     *
     * This REVERSES the "roles stay reusable across divisions" stance
     * from earlier in this project's history — re-confirmed directly by
     * the company owner: a role like "Admin" should belong to exactly
     * one division, even if the same person ends up holding several
     * different roles (one per division) rather than one reusable role
     * spanning many. Nullable + nullOnDelete for the same reason as
     * branch_office_id: existing/legacy roles created before this column
     * existed keep working as company-wide (or branch-wide) roles, and
     * deleting a division reverts any role locked to it back to that
     * wider scope instead of destroying the role.
     */
    public function up(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->foreignUuid('branch_office_unit_id')
                ->nullable()
                ->after('branch_office_id')
                ->constrained('branch_office_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_office_unit_id');
        });
    }
};
