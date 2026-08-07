<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers Jadwal\JadwalMessageTemplateController's routes
 * ('jadwal.pengaturan-pesan.*') in the App\Models\ApplicationMenu
 * catalog — same reasoning as 2026_08_07_120000_seed_jadwal_kelas_
 * application_menu: App\Http\Middleware\EnsureMenuAccess matches by a
 * route name's first TWO dot-segments ("jadwal.pengaturan-pesan" here),
 * so without its own catalog row this whole prefix would fail OPEN
 * (unrestricted for every non-owner member) instead of being grantable
 * per CompanyRole like the rest of the Jadwal module.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', ['jadwal'])
            ->value('id');

        if (! $categoryId) {
            return; // Jadwal category should already exist by this point — nothing to attach to otherwise.
        }

        $exists = DB::table('application_menus')
            ->where('route_name', 'jadwal.pengaturan-pesan.index')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('application_menus')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'category_application_id' => $categoryId,
            'parent_id' => null,
            'name' => 'Pengaturan Pesan',
            'route_name' => 'jadwal.pengaturan-pesan.index',
            'icon' => 'ri-message-3-line',
            'sort_order' => 30,
            'description' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('application_menus')->where('route_name', 'jadwal.pengaturan-pesan.index')->delete();
    }
};
