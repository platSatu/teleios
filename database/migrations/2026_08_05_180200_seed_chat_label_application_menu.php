<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the new "Label" page (Chat > Pengaturan > Label, route
 * chat.labels.index) to the App\Models\ApplicationMenu catalog — same
 * one-off seed pattern as 2026_08_03_170200_seed_chat_application_menu_catalog.
 * Without this row, a company member with a restricted CompanyRoleMenu
 * grant list could never be granted access to the new page — the
 * sidebar's $canSeeChatMenu() check (see resources/views/layouts/
 * partials/menu.blade.php) and EnsureMenuAccess middleware both key off
 * this catalog, not the route existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
            ->orderBy('created_at')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        $exists = DB::table('application_menus')
            ->where('route_name', 'chat.labels.index')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('application_menus')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'category_application_id' => $categoryId,
            'parent_id' => null,
            'name' => 'Label',
            'route_name' => 'chat.labels.index',
            'icon' => 'ri-price-tag-3-line',
            'sort_order' => 80,
            'description' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('application_menus')->where('route_name', 'chat.labels.index')->delete();
    }
};
