<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverses 2026_08_11_090200. That table turned out to be a
     * duplicate concern: company_role_menus (2026_07_31_040000) already
     * carries company_role_id + category_application_id (alongside
     * application_menu_id) — a role's allowed Category Applications are
     * already fully expressed as "every category_application_id that
     * shows up across that role's company_role_menus rows", so a
     * separate role<->category_application pivot added nothing except a
     * second place the same fact could drift out of sync. The "Add
     * Application" step now writes directly to company_role_menus (see
     * App\Http\Controllers\User\Profile\CompanyRoleMenuController)
     * instead.
     */
    public function up(): void
    {
        Schema::dropIfExists('company_role_category_applications');
    }

    public function down(): void
    {
        // Deliberately not recreated — see 2026_08_11_090200 if this
        // table is ever needed again; down() here is a no-op since the
        // table this migration removes is superseded, not meant to come
        // back.
    }
};
