<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CompanyRole — the "Roles" tab on dashboard/user/
     * profile. A default "Owner" role is created automatically for
     * every company the moment it's created (see User\Profile\
     * ProfileController::updateCompany()); everything else is managed
     * by the company owner via User\Profile\CompanyRoleController.
     */
    public function up(): void
    {
        Schema::create('company_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_roles');
    }
};
