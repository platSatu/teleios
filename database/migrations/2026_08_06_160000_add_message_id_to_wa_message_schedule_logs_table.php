<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds real delivered/read tracking to "Pesan Terjadwal" — until now,
     * the "Delivered" column on the index page was actually showing
     * `status = 'sent'` (meaning only "handed off to the Go backend
     * successfully"), and "Read" was a hardcoded placeholder, because
     * nothing in this app ever captured WhatsApp's own delivery/read
     * receipts for a scheduled send.
     *
     * `message_id` is WhatsApp's own message id (g_backend's
     * WaMessage.MessageID, returned as `message_id` in the JSON body
     * App\Services\Chat\InboxService::send() returns) — captured by
     * App\Jobs\SendScheduledWaMessage the moment a send succeeds, so a
     * later delivery/read receipt (forwarded by g_backend's
     * UpdateMessageStatus via a new webhook — see
     * App\Http\Controllers\Api\WaMessageStatusWebhookController) can be
     * matched back to the row that sent it.
     *
     * `status` itself gets no new DB-level constraint (it never had an
     * enum check to begin with) — it just now legitimately holds
     * 'delivered'/'read' in addition to the original pending/sent/failed,
     * written by WaMessageStatusWebhookController, rank-gated the same
     * "never move backwards" way g_backend's own messageStatusRank()
     * already protects wa_messages.status.
     */
    public function up(): void
    {
        Schema::table('wa_message_schedule_logs', function (Blueprint $table) {
            $table->string('message_id', 64)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedule_logs', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
    }
};
