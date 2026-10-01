<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Layanan baru "Marketplace" di katalog Category Application + menu-menunya
 * (Application Menu), supaya:
 *   - superadmin bisa membuat paket yang mencakup layanan ini
 *     (gate 'active.package:Marketplace', App\Support\MenuGateCategories),
 *   - owner bisa memberikan menu Lazada ke role staff (tab Applications,
 *     CompanyContext::canAccessRoute()).
 *
 * Idempotent: category dicari dulu berdasarkan nama, menu yang route_name-nya
 * sudah ada tidak diubah (hasil edit superadmin aman).
 */
return new class extends Migration
{
    private const CATEGORY_NAME = 'Marketplace';

    private const MENUS = [
        'marketplace.lazada.shops.index' => 'Lazada - Toko',
        'marketplace.lazada.orders.index' => 'Lazada - Pesanan',
    ];

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', [strtolower(self::CATEGORY_NAME)])
            ->value('id');

        if (! $categoryId) {
            $categoryId = (string) Str::uuid();

            DB::table('category_applications')->insert([
                'id' => $categoryId,
                'name' => self::CATEGORY_NAME,
                'description' => 'Hubungkan toko marketplace (Lazada) dan pantau pesanannya dari satu tempat.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existing = DB::table('application_menus')
            ->whereIn('route_name', array_keys(self::MENUS))
            ->pluck('route_name')
            ->all();

        $order = 0;

        foreach (self::MENUS as $routeName => $name) {
            $order += 10;

            if (in_array($routeName, $existing, true)) {
                continue;
            }

            DB::table('application_menus')->insert([
                'id' => (string) Str::uuid(),
                'category_application_id' => $categoryId,
                'name' => $name,
                'route_name' => $routeName,
                'sort_order' => $order,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja dibiarkan: category bisa sudah dipakai paket, menu bisa
        // sudah diberikan ke role.
    }
};
