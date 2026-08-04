<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CompanyToUser — pivot-with-attributes linking a
     * user to a company under a specific CompanyRole. One row is
     * created automatically (role "Owner", status active) the moment a
     * user creates their company — see User\Profile\
     * ProfileController::updateCompany(). Everything after that is
     * managed via the "Setting Users" tab / User\Profile\
     * CompanyUserController.
     */
    public function up(): void
    {
        Schema::create('company_to_users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // restrictOnDelete (not cascade): deleting a role that's
            // still assigned to members should fail loudly and force an
            // explicit reassignment first, rather than silently kicking
            // people out of the company (which cascade would do here —
            // including, worst case, silently removing the owner's own
            // membership if "Owner" were ever deleted).
            $table->foreignUuid('company_role_id')
                ->constrained('company_roles')
                ->restrictOnDelete();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            // One membership row per user per company.
            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_to_users');
    }
};
