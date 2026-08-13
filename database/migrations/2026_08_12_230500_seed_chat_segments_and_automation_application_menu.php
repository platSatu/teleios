<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the two new CRM Roadmap Fase 4 pages (Chat > Segmentasi, route
 * chat.segments.index; Chat > Automasi, route chat.automation-rules.
 * index) to the App\Models\ApplicationMenu catalog — same one-off seed
 * pattern as 2026_08_12_210100_seed_chat_tasks_application_menu and
 * 2026_08_12_220100_seed_chat_deals_application_menu. See either of
 * those migrations' docblocks for why this row is required for
 * restricted roles to ever be grantable access.
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

        $entries = [
            ['route_name' => 'chat.segments.index', 'name' => 'Segmentasi', 'icon' => 'ri-price-tag-3-line', 'sort_order' => 78],
            ['route_name' => 'chat.automation-rules.index', 'name' => 'Automasi', 'icon' => 'ri-flow-chart', 'sort_order' => 79],
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
            'chat.segments.index',
            'chat.automation-rules.index',
        ])->delete();
    }
};
