<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the new "Tugas & Follow-up" page (Chat > Tugas & Follow-up,
 * route chat.tasks.index) to the App\Models\ApplicationMenu catalog —
 * same one-off seed pattern as
 * 2026_08_05_200100_seed_chat_contacts_application_menu. Without this
 * row, a company member with a restricted CompanyRoleMenu grant list
 * could never be granted access to the new page (see
 * App\Providers\AppServiceProvider's $allowedChatRouteNames view
 * composer — it's built strictly from ApplicationMenu rows joined
 * through CompanyRoleMenu, so an uncatalogued route can never appear
 * in any role's allow-list, unlike App\Http\Middleware\EnsureMenuAccess
 * which fails open).
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
            ->where('route_name', 'chat.tasks.index')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('application_menus')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'category_application_id' => $categoryId,
            'parent_id' => null,
            'name' => 'Tugas & Follow-up',
            'route_name' => 'chat.tasks.index',
            'icon' => 'ri-task-line',
            'sort_order' => 76,
            'description' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('application_menus')->where('route_name', 'chat.tasks.index')->delete();
    }
};
