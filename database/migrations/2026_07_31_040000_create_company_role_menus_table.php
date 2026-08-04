<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CompanyRoleMenu — which Application Menu entries
     * (from the App\Models\ApplicationMenu catalog, grouped by Category
     * Application) a company has switched on. Managed by the company
     * owner from the "Applications" tab on dashboard/user/profile (see
     * User\Profile\CompanyRoleMenuController), and superadmin has a
     * cross-company view/CRUD over the same table (Superadmin\
     * CompanyRoleMenuController) to fix things when a company reports a
     * missing/wrong menu.
     */
    public function up(): void
    {
        Schema::create('company_role_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // restrictOnDelete on both catalog FKs (same reasoning as
            // packages.category_application_id / application_menus.
            // category_application_id): a category or menu that's
            // already assigned to a company can't be deleted out from
            // under it.
            $table->foreignUuid('category_application_id')
                ->constrained('category_applications')
                ->restrictOnDelete();

            $table->foreignUuid('application_menu_id')
                ->constrained('application_menus')
                ->restrictOnDelete();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            // A company can only switch a given menu on once.
            $table->unique(['company_id', 'application_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_menus');
    }
};
