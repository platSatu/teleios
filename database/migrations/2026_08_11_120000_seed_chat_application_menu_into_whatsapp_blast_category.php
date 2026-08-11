<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Follow-up to 2026_08_11_110000_sync_application_menu_names_with_sidebar,
 * which turned out to be a no-op on this environment: every earlier Chat
 * catalog seeder (2026_08_03_170200, 2026_08_05_180200, 2026_08_05_200100,
 * 2026_08_06_140000, 2026_08_06_150200) looks up its target category via
 * `LOWER(name) LIKE '%chat%'`, but the real Chat-equivalent category in
 * this database is named "Whatsapp Blast" — never matched that pattern —
 * so all of them silently no-op'd from day one and none of the real
 * chat.* App\Models\ApplicationMenu rows were ever created here. The
 * "Whatsapp Blast" category only had 4 unrelated hand-made rows (Create/
 * Edit/Hapus/Index, no route_name) left over from manual testing.
 *
 * Widens the category lookup to also match "Whatsapp Blast" (and
 * "whatsapp" generally, in case a future environment names it slightly
 * differently again), then seeds the full real Chat menu catalog into
 * it — same list/names as 2026_08_11_110000, since that migration's
 * name-correction logic is still correct, it just never had a row to
 * apply to here. UPDATE-if-exists / INSERT-if-missing, same idempotent
 * shape as every seeder before it, so re-running this is always safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('category_applications')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%whatsapp%']);
            })
            ->orderBy('created_at')
            ->value('id');

        if (! $categoryId) {
            return; // No Chat/Whatsapp-like category in this environment at all — nothing to attach to.
        }

        $entries = [
            ['route_name' => 'chat.connect-device.index', 'name' => 'Device / Inbox', 'icon' => 'ri-smartphone-line', 'sort_order' => 10],
            ['route_name' => 'chat.message-schedules.index', 'name' => 'Pesan Terjadwal', 'icon' => 'ri-calendar-schedule-line', 'sort_order' => 20],
            ['route_name' => 'chat.message-templates.index', 'name' => 'WA Template', 'icon' => 'ri-file-text-line', 'sort_order' => 30],
            ['route_name' => 'chat.category-templates.index', 'name' => 'Kategori Template', 'icon' => 'ri-price-tag-2-line', 'sort_order' => 35],
            ['route_name' => 'chat.message-auto-replies.index', 'name' => 'Auto Reply (Kata Kunci)', 'icon' => 'ri-chat-check-line', 'sort_order' => 40],
            ['route_name' => 'chat.message-quick-replies.index', 'name' => 'Balasan Cepat', 'icon' => 'ri-flashlight-line', 'sort_order' => 60],
            ['route_name' => 'chat.ai-bots.index', 'name' => 'AI Bot', 'icon' => 'ri-robot-line', 'sort_order' => 70],
            ['route_name' => 'chat.labels.index', 'name' => 'Label', 'icon' => 'ri-price-tag-3-line', 'sort_order' => 80],
            // Buku Telepon submenu — plain labels, no "Buku Telepon - " prefix (see 2026_08_11_110000's docblock).
            ['route_name' => 'chat.phone-books.index', 'name' => 'Kontak', 'icon' => 'ri-contacts-book-line', 'sort_order' => 90],
            ['route_name' => 'chat.contacts.index', 'name' => 'Riwayat Kontak', 'icon' => 'ri-contacts-book-2-line', 'sort_order' => 95],
            ['route_name' => 'chat.category-phone-books.index', 'name' => 'Kelompok', 'icon' => 'ri-group-line', 'sort_order' => 100],
            ['route_name' => 'chat.wa-groups.index', 'name' => 'WA Group', 'icon' => 'ri-team-line', 'sort_order' => 110],
            ['route_name' => 'chat.google-contacts.index', 'name' => 'Google Contact', 'icon' => 'ri-google-line', 'sort_order' => 120],
            // Third Party submenu — plain label, no "Third Party - " prefix.
            ['route_name' => 'chat.third-party.google-form.index', 'name' => 'Google Form', 'icon' => 'ri-google-line', 'sort_order' => 130],
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

    /**
     * Only removes rows this migration itself could have created (matched
     * by route_name) — never touches the pre-existing Create/Edit/Hapus/
     * Index manual test rows, and never touches the category row itself.
     */
    public function down(): void
    {
        DB::table('application_menus')->whereIn('route_name', [
            'chat.connect-device.index',
            'chat.message-schedules.index',
            'chat.message-templates.index',
            'chat.category-templates.index',
            'chat.message-auto-replies.index',
            'chat.message-quick-replies.index',
            'chat.ai-bots.index',
            'chat.labels.index',
            'chat.phone-books.index',
            'chat.contacts.index',
            'chat.category-phone-books.index',
            'chat.wa-groups.index',
            'chat.google-contacts.index',
            'chat.third-party.google-form.index',
        ])->delete();
    }
};
