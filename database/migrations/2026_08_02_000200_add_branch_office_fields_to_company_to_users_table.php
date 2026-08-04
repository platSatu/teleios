<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a company owner optionally place a member (company_to_users
     * row) under a specific branch office / unit when adding or editing
     * them from the "Setting Users" tab. Both nullable: a member doesn't
     * have to belong to a branch office/unit (mirrors category_
     * application_id's nullable = "unrestricted" convention on this same
     * table), and a company that hasn't set up branch offices yet can
     * keep adding users as before.
     *
     * nullOnDelete (not cascade/restrict): deleting a branch office or
     * unit should just clear the assignment on affected members rather
     * than deleting the member's company_to_users row (cascade) or
     * blocking the delete outright (restrict).
     */
    public function up(): void
    {
        Schema::table('company_to_users', function (Blueprint $table) {
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->after('company_role_id')
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('branch_office_unit_id')
                ->nullable()
                ->after('branch_office_id')
                ->constrained('branch_office_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_to_users', function (Blueprint $table) {
            $table->dropForeign(['branch_office_unit_id']);
            $table->dropColumn('branch_office_unit_id');

            $table->dropForeign(['branch_office_id']);
            $table->dropColumn('branch_office_id');
        });
    }
};
