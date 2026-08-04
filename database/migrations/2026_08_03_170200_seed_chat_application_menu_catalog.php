<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Populates App\Models\ApplicationMenu with one row per REAL working
     * Chat route — these are the only entries the new role-scoped sidebar
     * (resources/views/layouts/partials/menu.blade.php) actually renders
     * dynamically. The rest of the Chat tree ("Buku Telepon", "Laporan")
     * are unfinished demo placeholders pointing at static theme HTML
     * files, not real controllers/routes, so they're deliberately left
     * out of this catalog and stay static/ungated in the view.
     *
     * Only runs if a Category Application that looks like "Chat" already
     * exists — this app has no fixed/seeded category names (superadmin
     * creates them freely from Superadmin\CategoryApplicationController),
     * so there's no guarantee one exists yet in every environment. If
     * none is found, this migration is a safe no-op: superadmin can
     * create the category and these ApplicationMenu rows by hand
     * afterwards from the existing "Application Menus" catalog screen,
     * this migration just saves that step when the obvious case applies.
     *
     * Idempotent: matched/upserted by `route_name`, so re-running this
     * (or a fresh install where it already ran) never creates duplicates.
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
            ['route_name' => 'chat.connect-device.index', 'name' => 'Device / Inbox', 'icon' => 'ri-smartphone-line', 'sort_order' => 10],
            ['route_name' => 'chat.message-schedules.index', 'name' => 'Pesan Terjadwal', 'icon' => 'ri-calendar-schedule-line', 'sort_order' => 20],
            ['route_name' => 'chat.message-templates.index', 'name' => 'WA Template', 'icon' => 'ri-file-text-line', 'sort_order' => 30],
            ['route_name' => 'chat.message-auto-replies.index', 'name' => 'Auto Reply (Kata Kunci)', 'icon' => 'ri-chat-check-line', 'sort_order' => 40],
            ['route_name' => 'chat.message-reminders.index', 'name' => 'Pengingat', 'icon' => 'ri-alarm-line', 'sort_order' => 50],
            ['route_name' => 'chat.message-quick-replies.index', 'name' => 'Balasan Cepat', 'icon' => 'ri-flashlight-line', 'sort_order' => 60],
            ['route_name' => 'chat.ai-bots.index', 'name' => 'AI Bot', 'icon' => 'ri-robot-line', 'sort_order' => 70],
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
     * Only removes rows this migration itself could have created (matched
     * by route_name) — never touches anything superadmin added by hand.
     */
    public function down(): void
    {
        DB::table('application_menus')->whereIn('route_name', [
            'chat.connect-device.index',
            'chat.message-schedules.index',
            'chat.message-templates.index',
            'chat.message-auto-replies.index',
            'chat.message-reminders.index',
            'chat.message-quick-replies.index',
            'chat.ai-bots.index',
        ])->delete();
    }
};
