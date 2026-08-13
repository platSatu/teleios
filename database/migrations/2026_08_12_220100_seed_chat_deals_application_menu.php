<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the new "Sales Pipeline" page (Chat > Sales Pipeline, route
 * chat.deals.index) to the App\Models\ApplicationMenu catalog — same
 * one-off seed pattern as
 * 2026_08_12_210100_seed_chat_tasks_application_menu. See that
 * migration's docblock for why this row is required for restricted
 * roles to ever be grantable access.
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
            ->where('route_name', 'chat.deals.index')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('application_menus')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'category_application_id' => $categoryId,
            'parent_id' => null,
            'name' => 'Sales Pipeline',
            'route_name' => 'chat.deals.index',
            'icon' => 'ri-funds-line',
            'sort_order' => 77,
            'description' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('application_menus')->where('route_name', 'chat.deals.index')->delete();
    }
};
