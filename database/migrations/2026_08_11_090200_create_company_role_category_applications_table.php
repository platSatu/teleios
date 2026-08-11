<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\CompanyRoleCategoryApplication — which
     * CategoryApplication(s) (the purchasable layanan, e.g. "Chat",
     * "Absensi") a given CompanyRole is allowed to touch at all.
     *
     * Deliberately a NEW table, not a reuse of company_role_menus
     * (2026_07_31_040000): that one governs ApplicationMenu (fine-grained
     * feature/menu toggles — the existing "Applications" tab on
     * dashboard/user/profile) and is a completely different axis from
     * CategoryApplication (the billable layanan a branch subscribes to).
     * Naming them the same thing would make both unreadable — see the
     * "Role → Add to Application" flow's docblock in User\Profile\
     * CompanyRoleCategoryApplicationController for how the two relate at
     * the UI level.
     *
     * No extra payload columns (no status/description) — this is a pure
     * many-to-many join, existence of the row IS the grant. Both FKs
     * cascade: a role or a category_application catalog entry going away
     * makes the grant meaningless, not just orphaned.
     *
     * Both FK constraints are given explicit short names — MySQL's
     * default `{table}_{column}_foreign` naming blows past its 64-char
     * identifier limit here because the table name itself is 35 chars
     * (`company_role_category_applications_category_application_id_foreign`
     * is 70). Same class of problem, same fix, as the explicit short
     * unique index name in 2026_07_31_060000.
     *
     * Plain auto-incrementing `id`, NOT uuid like almost every other
     * table in this app — deliberately. This pivot is written via
     * CompanyRole::categoryApplications()->sync() (App\Http\Controllers\
     * User\Profile\Setup\ApplicationController::update()), and Eloquent's
     * attach()/sync() do a raw bulk insert into the pivot table that
     * bypasses model events entirely — a uuid primary key generated in a
     * model's creating() hook (this codebase's usual pattern, e.g.
     * App\Models\CompanyToUser) would simply never run, and MySQL would
     * reject every sync() with a null-primary-key error. An
     * auto-increment column needs no such hook, so sync() works
     * out of the box.
     */
    public function up(): void
    {
        Schema::create('company_role_category_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('company_role_id')
                ->constrained('company_roles', indexName: 'crca_company_role_foreign')
                ->cascadeOnDelete();

            $table->foreignUuid('category_application_id')
                ->constrained('category_applications', indexName: 'crca_category_application_foreign')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['company_role_id', 'category_application_id'],
                'company_role_category_apps_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_category_applications');
    }
};
