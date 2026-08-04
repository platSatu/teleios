<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execution-engine bookkeeping for App\Http\Controllers\Api\
     * WaIncomingMessageWebhookController + App\Jobs\SendAutoReplyMessage
     * — unlike Pesan Terjadwal/Balasan Otomatis this rule can fire
     * repeatedly (once per matching incoming message), so there's no
     * single sent_at; instead:
     *
     * - last_triggered_at: when this rule last actually sent a reply —
     *   surfaced in the "Auto Reply (Kata Kunci)" list so a company
     *   owner can see the rule is alive instead of it being a black box.
     * - trigger_count: how many times total, mostly for the same
     *   "is this actually doing anything" visibility.
     * - last_error: the most recent send failure, same idea as the
     *   other two chat execution engines.
     */
    public function up(): void
    {
        Schema::table('wa_message_auto_replies', function (Blueprint $table) {
            $table->timestamp('last_triggered_at')->nullable()->after('status');
            $table->unsignedInteger('trigger_count')->default(0)->after('last_triggered_at');
            $table->text('last_error')->nullable()->after('trigger_count');

            // WaIncomingMessageWebhookController looks up "every active
            // rule for this device" on every single incoming message —
            // this is the hot path, so it gets an index.
            $table->index(['device_id', 'status'], 'wa_message_auto_replies_device_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_auto_replies', function (Blueprint $table) {
            $table->dropIndex('wa_message_auto_replies_device_status_index');
            $table->dropColumn(['last_triggered_at', 'trigger_count', 'last_error']);
        });
    }
};
