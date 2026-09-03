<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks whether members holding this CompanyRole should be offered
     * as "Pengajar" in the Jadwal module (JadwalKelasController,
     * JadwalPengajarController, JadwalRutinController,
     * JadwalStudentController — see
     * ResolvesCompanyContext::companyPengajarMembers()).
     *
     * Before this column, every active company member — including the
     * Owner, unconditionally — showed up in every Pengajar dropdown,
     * since there was no way to tell a teaching role from a
     * non-teaching one (Admin, Finance, ...).
     *
     * Defaults to true so the migration itself is a no-op for every
     * role that already exists: nobody who was showing up as a
     * pengajar before this deploy silently disappears from a dropdown
     * the moment it ships. Company owners can then opt individual
     * roles out from the Roles tab. Freshly-created custom roles flip
     * this default in the application layer instead (see
     * CompanyRoleController::store(), which defaults an unchecked
     * "Bisa jadi Pengajar?" box to false for a brand new role) — the
     * DB-level true only exists to protect the existing/legacy rows
     * this migration runs against.
     */
    public function up(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->boolean('is_pengajar')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('company_roles', function (Blueprint $table) {
            $table->dropColumn('is_pengajar');
        });
    }
};
