<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Registers the new "Third Party > Google Form" route
     * (chat.third-party.google-form.index) in the same
     * App\Models\ApplicationMenu catalog
     * 2026_08_06_140000_seed_buku_telepon_application_menu_catalog.php
     * seeded — see that migration's docblock for the full rationale.
     * Without this, a branch-locked (non-owner) company member would
     * never see the new sidebar item regardless of the menu.blade.php
     * change, since $canSeeChatMenu() only shows routes present in the
     * caller's allowed set once one has been configured.
     */
    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
            ->orderBy('created_at')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        $entries = [
            ['route_name' => 'chat.third-party.google-form.index', 'name' => 'Third Party - Google Form', 'icon' => 'ri-google-line', 'sort_order' => 120],
        ];

        foreach ($entries as $entry) {
            $exists = DB::table('application_menus')
                ->where('route_name', $entry['route_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('application_menus')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'category_application_id' => $categoryId,
                'parent_id' => null,
                'name' => $entry['name'],
                'route_name' => $entry['route_name'],
                'icon' => $entry['icon'],
                'sort_order' => $entry['sort_order'],
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
            'chat.third-party.google-form.index',
        ])->delete();
    }
};
