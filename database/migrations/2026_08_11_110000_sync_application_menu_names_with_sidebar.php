<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Re-syncs every Chat/Jadwal App\Models\ApplicationMenu row's `name`
 * column with the label actually shown for it today in
 * resources/views/layouts/partials/menu.blade.php.
 *
 * The sidebar's wording drifted away from the catalog over time — several
 * Chat menu items got nested under a "Buku Telepon"/"Third Party" submenu
 * (dropping the "Buku Telepon - " / "Third Party - " prefix from their
 * label, since the submenu heading already gives that context) or got
 * renamed outright (chat.contacts.index: "Kontak" -> "Riwayat Kontak")
 * AFTER the seed migrations below already ran and inserted the old name:
 *
 *   - 2026_08_05_200100_seed_chat_contacts_application_menu ("Kontak")
 *   - 2026_08_06_140000_seed_buku_telepon_application_menu_catalog
 *     ("Buku Telepon - Kontak" / "- Kelompok" / "- WA Group" /
 *     "- Google Contact")
 *   - 2026_08_06_150200_seed_third_party_application_menu_catalog
 *     ("Third Party - Google Form")
 *
 * Since App\Models\ApplicationMenu is what the owner actually picks from
 * on the "Applications" tab (User\Profile\CompanyRoleMenuController) when
 * granting a role access to a page, a stale name here means the owner is
 * choosing from labels that no longer match what their team sees in the
 * sidebar — confusing, even though functionally harmless (route_name is
 * still the real key everywhere else).
 *
 * UPDATE-if-exists (matched by route_name, since that's the real
 * identity — see every migration above), INSERT-if-missing as a
 * self-healing fallback for an environment where the category didn't
 * exist yet when an earlier seeder ran and it no-op'd. Only touches
 * `name` on existing rows — icon/sort_order/status are left exactly as
 * they are, in case a superadmin already customized them by hand from
 * the "Application Menus" catalog screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $chatCategoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
            ->orderBy('created_at')
            ->value('id');

        $jadwalCategoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', ['jadwal'])
            ->value('id');

        // route_name => [category id to insert under if missing, name, icon, sort_order]
        $entries = [
            ['route_name' => 'chat.connect-device.index', 'category_id' => $chatCategoryId, 'name' => 'Device / Inbox', 'icon' => 'ri-smartphone-line', 'sort_order' => 10],
            ['route_name' => 'chat.message-schedules.index', 'category_id' => $chatCategoryId, 'name' => 'Pesan Terjadwal', 'icon' => 'ri-calendar-schedule-line', 'sort_order' => 20],
            ['route_name' => 'chat.message-templates.index', 'category_id' => $chatCategoryId, 'name' => 'WA Template', 'icon' => 'ri-file-text-line', 'sort_order' => 30],
            ['route_name' => 'chat.category-templates.index', 'category_id' => $chatCategoryId, 'name' => 'Kategori Template', 'icon' => 'ri-price-tag-2-line', 'sort_order' => 35],
            ['route_name' => 'chat.message-auto-replies.index', 'category_id' => $chatCategoryId, 'name' => 'Auto Reply (Kata Kunci)', 'icon' => 'ri-chat-check-line', 'sort_order' => 40],
            ['route_name' => 'chat.message-quick-replies.index', 'category_id' => $chatCategoryId, 'name' => 'Balasan Cepat', 'icon' => 'ri-flashlight-line', 'sort_order' => 60],
            ['route_name' => 'chat.ai-bots.index', 'category_id' => $chatCategoryId, 'name' => 'AI Bot', 'icon' => 'ri-robot-line', 'sort_order' => 70],
            ['route_name' => 'chat.labels.index', 'category_id' => $chatCategoryId, 'name' => 'Label', 'icon' => 'ri-price-tag-3-line', 'sort_order' => 80],
            // Buku Telepon submenu — plain labels now, no "Buku Telepon - " prefix.
            ['route_name' => 'chat.phone-books.index', 'category_id' => $chatCategoryId, 'name' => 'Kontak', 'icon' => 'ri-contacts-book-line', 'sort_order' => 90],
            ['route_name' => 'chat.contacts.index', 'category_id' => $chatCategoryId, 'name' => 'Riwayat Kontak', 'icon' => 'ri-contacts-book-2-line', 'sort_order' => 95],
            ['route_name' => 'chat.category-phone-books.index', 'category_id' => $chatCategoryId, 'name' => 'Kelompok', 'icon' => 'ri-group-line', 'sort_order' => 100],
            ['route_name' => 'chat.wa-groups.index', 'category_id' => $chatCategoryId, 'name' => 'WA Group', 'icon' => 'ri-team-line', 'sort_order' => 110],
            ['route_name' => 'chat.google-contacts.index', 'category_id' => $chatCategoryId, 'name' => 'Google Contact', 'icon' => 'ri-google-line', 'sort_order' => 120],
            // Third Party submenu — plain label, no "Third Party - " prefix.
            ['route_name' => 'chat.third-party.google-form.index', 'category_id' => $chatCategoryId, 'name' => 'Google Form', 'icon' => 'ri-google-line', 'sort_order' => 130],
            // Jadwal — already matched the sidebar, included so this
            // migration is the one complete source of truth going forward.
            ['route_name' => 'jadwal.mata-pelajaran.index', 'category_id' => $jadwalCategoryId, 'name' => 'Mata Pelajaran', 'icon' => 'ri-book-2-line', 'sort_order' => 10],
            ['route_name' => 'jadwal.jadwal-kelas.index', 'category_id' => $jadwalCategoryId, 'name' => 'Jadwal Kelas', 'icon' => 'ri-calendar-2-line', 'sort_order' => 20],
            ['route_name' => 'jadwal.pengaturan-pesan.index', 'category_id' => $jadwalCategoryId, 'name' => 'Pengaturan Pesan', 'icon' => 'ri-message-3-line', 'sort_order' => 30],
        ];

        foreach ($entries as $entry) {
            $existingId = DB::table('application_menus')
                ->where('route_name', $entry['route_name'])
                ->value('id');

            if ($existingId) {
                DB::table('application_menus')
                    ->where('id', $existingId)
                    ->update([
                        'name' => $entry['name'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            // Self-healing fallback only — nothing to attach a brand new
            // row to if its category doesn't exist in this environment.
            if (! $entry['category_id']) {
                continue;
            }

            DB::table('application_menus')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'category_application_id' => $entry['category_id'],
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

    /**
     * No down(): this migration only ever corrects a `name` value that
     * earlier migrations already own the insert/delete for (see the
     * list in this file's docblock) — rolling back a spelling fix isn't
     * meaningful, and the self-healing insert path uses the exact same
     * route_name those migrations already clean up in their own down().
     */
    public function down(): void
    {
        //
    }
};
