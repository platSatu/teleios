<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes "Pengingat" (App\Models\WaMessageReminder /
 * Chat\MessageReminderController) entirely — it was a thin CRUD with no
 * dispatcher/job/command anywhere ever reading wa_message_reminders and
 * actually sending anything, so every row a company created there
 * silently never went out. "Jadwal Pesan" (App\Models\WaMessageSchedule,
 * type='once') already covers the exact same capability (single date,
 * single recipient, free text) and genuinely sends, via
 * App\Console\Commands\DispatchDueWaMessageSchedules.
 *
 * Deletion order matters: company_role_menus.application_menu_id has a
 * restrictOnDelete() FK into application_menus (see
 * 2026_07_31_040000_create_company_role_menus_table), so any company
 * that had switched "Pengingat" on in their Applications tab must have
 * that grant row deleted first, before the application_menus catalog
 * row itself can be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('application_menus')
            ->where('route_name', 'chat.message-reminders.index')
            ->value('id');

        if ($menuId) {
            DB::table('company_role_menus')->where('application_menu_id', $menuId)->delete();
            DB::table('application_menus')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('wa_message_reminders');
    }

    /**
     * Deliberately no down(): the table's data (and each company's menu
     * grant) is gone once this runs, same as every other "delete a
     * dead feature" migration in this app — recreating the empty shell
     * wouldn't restore anything meaningful anyway.
     */
    public function down(): void
    {
        //
    }
};
