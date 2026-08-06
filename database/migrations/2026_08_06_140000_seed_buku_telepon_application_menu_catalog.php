<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds the now-real "Buku Telepon" routes (Kontak/Kelompok/WA Group/
     * Google Contact/Blacklist) to the same App\Models\ApplicationMenu
     * catalog 2026_08_03_170200_seed_chat_application_menu_catalog.php
     * seeded — that migration's docblock explicitly left "Buku Telepon"
     * out because at the time it was only dead demo links, not real
     * controllers. Now that Chat\PhoneBookController/
     * CategoryPhoneBookController/WaGroupController/GoogleContactController
     * exist, this registers them so a superadmin can grant a branch-
     * locked CompanyRole access to them the same way as every other Chat
     * menu item — without this, a role-restricted (non-owner) member
     * would never see these links regardless of the sidebar change,
     * since $canSeeChatMenu() in menu.blade.php only shows routes
     * present in the caller's allowed set once one has been configured.
     *
     * Same idempotent-by-route_name / "only if a Chat-like category
     * exists" / down()-only-removes-what-this-added rules as the
     * migration above — see that one's docblock for the full rationale.
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
            ['route_name' => 'chat.phone-books.index', 'name' => 'Buku Telepon - Kontak', 'icon' => 'ri-contacts-book-line', 'sort_order' => 80],
            ['route_name' => 'chat.category-phone-books.index', 'name' => 'Buku Telepon - Kelompok', 'icon' => 'ri-group-line', 'sort_order' => 90],
            ['route_name' => 'chat.wa-groups.index', 'name' => 'Buku Telepon - WA Group', 'icon' => 'ri-team-line', 'sort_order' => 100],
            ['route_name' => 'chat.google-contacts.index', 'name' => 'Buku Telepon - Google Contact', 'icon' => 'ri-google-line', 'sort_order' => 110],
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
            'chat.phone-books.index',
            'chat.category-phone-books.index',
            'chat.wa-groups.index',
            'chat.google-contacts.index',
        ])->delete();
    }
};
