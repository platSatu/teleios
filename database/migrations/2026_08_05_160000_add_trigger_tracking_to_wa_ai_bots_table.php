<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same trigger-tracking columns as
 * 2026_08_02_120000_add_trigger_tracking_to_wa_message_auto_replies_table,
 * now for App\Models\WaAiBot — App\Jobs\SendAiBotReply writes to these
 * on every run so a company owner can see the bot is alive (and why a
 * send failed) instead of it being a black box, same as the keyword
 * auto-reply engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wa_ai_bots', 'last_triggered_at')) {
            Schema::table('wa_ai_bots', function (Blueprint $table) {
                $table->timestamp('last_triggered_at')->nullable()->after('status');
                $table->unsignedInteger('trigger_count')->default(0)->after('last_triggered_at');
                $table->text('last_error')->nullable()->after('trigger_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wa_ai_bots', 'last_triggered_at')) {
            Schema::table('wa_ai_bots', function (Blueprint $table) {
                $table->dropColumn(['last_triggered_at', 'trigger_count', 'last_error']);
            });
        }
    }
};
