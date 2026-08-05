<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the new "Kontak" page (Chat > Kontak, route chat.contacts.index)
 * to the App\Models\ApplicationMenu catalog — same one-off seed pattern
 * as 2026_08_05_180200_seed_chat_label_application_menu. Without this
 * row, a company member with a restricted CompanyRoleMenu grant list
 * could never be granted access to the new page.
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
            ->where('route_name', 'chat.contacts.index')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('application_menus')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'category_application_id' => $categoryId,
            'parent_id' => null,
            'name' => 'Kontak',
            'route_name' => 'chat.contacts.index',
            'icon' => 'ri-contacts-book-2-line',
            'sort_order' => 75,
            'description' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('application_menus')->where('route_name', 'chat.contacts.index')->delete();
    }
};
