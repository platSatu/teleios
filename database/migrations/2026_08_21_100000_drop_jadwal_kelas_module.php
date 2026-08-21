<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permanently removes the "Jadwal" (Jadwal Kelas / class-scheduling)
 * module — a separately-monetized paid package (gated by
 * 'active.package:Jadwal', see App\Http\Middleware\EnsureActivePackage)
 * removed from the app on 2026-08-21 after confirming no company was
 * still subscribed to or using it.
 *
 * This is a NEW forward migration rather than editing/deleting the
 * module's original 11 "create"/"seed" migrations (2026_08_07_110200
 * through 2026_08_07_170000) — those are left untouched so migration
 * history stays intact for any environment that already ran them; this
 * migration just undoes their effect going forward.
 *
 * Companion code cleanup (models, Jadwal\* controllers, Services\Jadwal,
 * the ProcessJadwalKelasReminders console command + its scheduler entry
 * in bootstrap/app.php, resources/views/jadwal, the jadwal.* routes, the
 * sidebar "Jadwal" menu section, and the 3 Jadwal-specific WA-reply
 * auto-confirm methods in App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController) was removed in the same change,
 * outside of migrations.
 *
 * Cleanup order below (catalog/grant rows first, then the module's own
 * tables) mirrors the FK constraints those rows and tables were created
 * with:
 *   1. company_role_menus rows granting the "Jadwal" category or any of
 *      its 3 application_menus rows to a CompanyRole — both
 *      company_role_menus.category_application_id and
 *      .application_menu_id are restrictOnDelete, so they must go before
 *      step 2/3 can succeed.
 *   2. packages rows under the "Jadwal" category — also restrictOnDelete
 *      on category_applications, so this must happen before step 3.
 *      package_limits rows cascade-delete with their package;
 *      vouchers.package_id / subscriptions.package_id are nullOnDelete,
 *      so purchase/redemption history survives with package_id reset to
 *      null rather than being blocked or deleted.
 *   3. the 3 seeded application_menus rows (also restrictOnDelete on
 *      category_applications, so before step 4).
 *   4. the "Jadwal" category_applications row itself.
 *   5. the module's own 7 tables. Foreign key checks are disabled for
 *      just the DROP TABLE statements as a belt-and-suspenders measure —
 *      the order below is already FK-safe on its own: jadwal_usulan_
 *      perubahan and jadwal_kelas_sesi_murid are dropped before the
 *      jadwal_kelas_sesi / jadwal_kelas_murid rows they reference, which
 *      are dropped before jadwal_kelas, which is dropped before
 *      mata_pelajaran; jadwal_message_templates has no inbound FKs from
 *      any of the others.
 */
return new class extends Migration
{
    private const MENU_ROUTE_NAMES = [
        'jadwal.mata-pelajaran.index',
        'jadwal.jadwal-kelas.index',
        'jadwal.pengaturan-pesan.index',
    ];

    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', ['jadwal'])
            ->value('id');

        $menuIds = DB::table('application_menus')
            ->whereIn('route_name', self::MENU_ROUTE_NAMES)
            ->pluck('id')
            ->all();

        // 1. company_role_menus grants. Guarded so an empty where() never
        // runs (which would delete every row in the table) when neither
        // the category nor any of the 3 menu rows exist any more (e.g.
        // this migration is re-run after already succeeding once).
        if ($categoryId || ! empty($menuIds)) {
            DB::table('company_role_menus')
                ->where(function ($query) use ($categoryId, $menuIds) {
                    if ($categoryId) {
                        $query->orWhere('category_application_id', $categoryId);
                    }

                    if (! empty($menuIds)) {
                        $query->orWhereIn('application_menu_id', $menuIds);
                    }
                })
                ->delete();
        }

        // 2. packages under the "Jadwal" category.
        if ($categoryId) {
            DB::table('packages')->where('category_application_id', $categoryId)->delete();
        }

        // 3. the 3 seeded application_menus rows.
        if (! empty($menuIds)) {
            DB::table('application_menus')->whereIn('id', $menuIds)->delete();
        }

        // 4. the "Jadwal" category_applications row.
        if ($categoryId) {
            DB::table('category_applications')->where('id', $categoryId)->delete();
        }

        // 5. the module's own tables.
        Schema::disableForeignKeyChecks();

        Schema::dropIfExists('jadwal_usulan_perubahan');
        Schema::dropIfExists('jadwal_kelas_sesi_murid');
        Schema::dropIfExists('jadwal_kelas_sesi');
        Schema::dropIfExists('jadwal_kelas_murid');
        Schema::dropIfExists('jadwal_kelas');
        Schema::dropIfExists('mata_pelajaran');
        Schema::dropIfExists('jadwal_message_templates');

        Schema::enableForeignKeyChecks();
    }

    /**
     * Deliberately a no-op: this migration is a permanent removal, not a
     * reversible schema change. Recreating the 7 tables would need the
     * original 11 migrations re-run in order (never deleted — see this
     * file's docblock), and re-inserting the exact same
     * category_applications / application_menus / packages /
     * company_role_menus rows this migration deleted isn't something
     * down() can safely reconstruct (their original ids and any
     * relations built on top of them are gone). Restore from a
     * pre-removal database backup instead, if this module is ever
     * needed again.
     */
    public function down(): void
    {
        // Intentional no-op — see docblock above.
    }
};
