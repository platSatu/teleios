<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers the new "Jadwal" module in the App\Models\CategoryApplication
 * / App\Models\ApplicationMenu catalog, so a company owner can actually
 * grant it to a CompanyRole via the existing "Applications" tab (User\
 * Profile\CompanyRoleMenuController) — same reasoning as
 * 2026_08_05_200100_seed_chat_contacts_application_menu: without a
 * catalog row, a role can never be granted access to the page at all,
 * since 'menu.access' middleware checks against App\Models\CompanyRoleMenu
 * grants for a non-owner member.
 *
 * Deliberately its own CategoryApplication ("Jadwal") rather than
 * reusing "Chat" — the sidebar entry is a sibling top-level menu, not
 * nested under Chat (per explicit spec), so it's granted independently
 * too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', ['jadwal'])
            ->value('id');

        if (! $categoryId) {
            $categoryId = (string) Str::uuid();

            DB::table('category_applications')->insert([
                'id' => $categoryId,
                'user_id' => null,
                'name' => 'Jadwal',
                'description' => 'Modul jadwal kelas — mata pelajaran, jadwal kelas, murid, dan notifikasi WhatsApp otomatis.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menus = [
            ['name' => 'Mata Pelajaran', 'route_name' => 'jadwal.mata-pelajaran.index', 'icon' => 'ri-book-2-line', 'sort_order' => 10],
            ['name' => 'Jadwal Kelas', 'route_name' => 'jadwal.jadwal-kelas.index', 'icon' => 'ri-calendar-2-line', 'sort_order' => 20],
        ];

        foreach ($menus as $menu) {
            $exists = DB::table('application_menus')
                ->where('route_name', $menu['route_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('application_menus')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'category_application_id' => $categoryId,
                'parent_id' => null,
                'name' => $menu['name'],
                'route_name' => $menu['route_name'],
                'icon' => $menu['icon'],
                'sort_order' => $menu['sort_order'],
                'description' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('application_menus')->whereIn('route_name', [
            'jadwal.mata-pelajaran.index',
            'jadwal.jadwal-kelas.index',
        ])->delete();

        DB::table('category_applications')->whereRaw('LOWER(name) = ?', ['jadwal'])->delete();
    }
};
