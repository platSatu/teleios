<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills every Chat route that SHOULD already be in
 * App\Models\ApplicationMenu — a consolidated, fully idempotent re-run of
 * every earlier Chat catalog seeder (2026_08_03_170200,
 * 2026_08_05_180200, 2026_08_05_200100, 2026_08_06_140000,
 * 2026_08_06_150200) plus one genuinely-never-seeded route
 * ('chat.category-templates.index' — has real routes/a real controller,
 * shown in the sidebar, but was missed by every earlier seeder).
 *
 * Why this exists as its own migration rather than just fixing the
 * originals: an environment can have those originals already marked as
 * "ran" in the `migrations` table (e.g. a deploy that ran migrate once
 * before all the app code/routes for a given Chat page existed yet, or a
 * database that was restored/rebuilt out of step with the app's migration
 * history) without the rows actually being present — Laravel never
 * re-runs a migration it already has a batch record for, no matter what
 * its up() would do differently now. This migration always executes
 * fresh, and every insert below is individually guarded by an
 * exists()-check on route_name, so it's a no-op for anything already
 * correctly seeded and only fills in whatever's actually missing.
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
            return; // Same as every prior Chat seeder: no Chat-like category yet, nothing to attach to.
        }

        $entries = [
            ['route_name' => 'chat.connect-device.index', 'name' => 'Device / Inbox', 'icon' => 'ri-smartphone-line', 'sort_order' => 10],
            ['route_name' => 'chat.message-schedules.index', 'name' => 'Pesan Terjadwal', 'icon' => 'ri-calendar-schedule-line', 'sort_order' => 20],
            ['route_name' => 'chat.message-templates.index', 'name' => 'WA Template', 'icon' => 'ri-file-text-line', 'sort_order' => 30],
            // The one route with no prior seed migration at all.
            ['route_name' => 'chat.category-templates.index', 'name' => 'Kategori Template', 'icon' => 'ri-price-tag-2-line', 'sort_order' => 35],
            ['route_name' => 'chat.message-auto-replies.index', 'name' => 'Auto Reply (Kata Kunci)', 'icon' => 'ri-chat-check-line', 'sort_order' => 40],
            ['route_name' => 'chat.message-quick-replies.index', 'name' => 'Balasan Cepat', 'icon' => 'ri-flashlight-line', 'sort_order' => 60],
            ['route_name' => 'chat.ai-bots.index', 'name' => 'AI Bot', 'icon' => 'ri-robot-line', 'sort_order' => 70],
            ['route_name' => 'chat.contacts.index', 'name' => 'Kontak', 'icon' => 'ri-contacts-book-2-line', 'sort_order' => 75],
            ['route_name' => 'chat.labels.index', 'name' => 'Label', 'icon' => 'ri-price-tag-3-line', 'sort_order' => 80],
            ['route_name' => 'chat.phone-books.index', 'name' => 'Buku Telepon - Kontak', 'icon' => 'ri-contacts-book-line', 'sort_order' => 80],
            ['route_name' => 'chat.category-phone-books.index', 'name' => 'Buku Telepon - Kelompok', 'icon' => 'ri-group-line', 'sort_order' => 90],
            ['route_name' => 'chat.wa-groups.index', 'name' => 'Buku Telepon - WA Group', 'icon' => 'ri-team-line', 'sort_order' => 100],
            ['route_name' => 'chat.google-contacts.index', 'name' => 'Buku Telepon - Google Contact', 'icon' => 'ri-google-line', 'sort_order' => 110],
            ['route_name' => 'chat.third-party.google-form.index', 'name' => 'Third Party - Google Form', 'icon' => 'ri-google-line', 'sort_order' => 120],
            // Deliberately NOT included: 'chat.message-reminders.index' —
            // removed for good in 2026_08_05_170000_remove_message_
            // reminder_feature (dead feature, nothing ever sent it).
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

    /**
     * No down(): this migration only ever fills gaps that earlier
     * migrations were already responsible for (and already own the
     * down() for) — the one genuinely new row ('chat.category-templates.
     * index') is left in place on rollback too, same reasoning as
     * 2026_08_05_170000's "removing a real catalog entry isn't something
     * a rollback should silently do".
     */
    public function down(): void
    {
        //
    }
};
