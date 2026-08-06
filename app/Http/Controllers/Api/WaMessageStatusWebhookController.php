<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaMessageScheduleLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives "a sent message's delivery/read status just advanced" from the
 * Go backend (see g_backend's WaInboxService.notifyMessageStatusWebhook,
 * fired from UpdateMessageStatus() as *events.Receipt events arrive from
 * whatsmeow) and updates the matching App\Models\WaMessageScheduleLog row
 * — this is what makes the "Delivered"/"Read" columns on the Pesan
 * Terjadwal index page (Chat\MessageScheduleController::index()) reflect
 * real WhatsApp receipts instead of "Delivered" actually meaning "the Go
 * backend accepted the send request" (status='sent') and "Read" being a
 * permanent placeholder.
 *
 * Only scheduled sends have a matching row here (App\Jobs\
 * SendScheduledWaMessage captures message_id the moment it sends) — a
 * receipt for a manually-sent inbox message just finds no match and is a
 * silent no-op, which is correct: this table only ever tracked scheduled
 * sends to begin with.
 */
class WaMessageStatusWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'status' => ['required', 'string', 'in:delivered,read,played'],
        ]);

        $log = WaMessageScheduleLog::where('message_id', $validated['message_id'])->first();

        if (! $log) {
            // Not one of ours (a manual inbox send, or a schedule log that
            // predates the message_id column) — nothing to update, but
            // still a 200 so g_backend doesn't log it as a failed delivery.
            return response()->json(['status' => 'no matching log, ignored']);
        }

        // 'played' (voice note listened to) ranks above 'read' on
        // g_backend's own ladder but this app's UI only ever shows
        // Delivered/Read columns — treat it as 'read' here rather than
        // introducing a status value nothing on this side displays.
        $incomingStatus = $validated['status'] === 'played' ? 'read' : $validated['status'];

        $incomingRank = WaMessageScheduleLog::STATUS_RANK[$incomingStatus] ?? 0;
        $currentRank = WaMessageScheduleLog::STATUS_RANK[$log->status] ?? 0;

        if ($incomingRank <= $currentRank) {
            // Never move backwards — same rule g_backend's own
            // messageStatusRank() applies to wa_messages.status, kept
            // consistent on this side too (e.g. a delayed 'delivered'
            // landing after 'read' already did).
            return response()->json(['status' => 'stale, ignored']);
        }

        $log->forceFill(['status' => $incomingStatus])->save();

        Log::info('wa-message-status: log updated', [
            'log_id' => $log->id,
            'message_id' => $validated['message_id'],
            'status' => $incomingStatus,
        ]);

        return response()->json(['status' => 'updated', 'log_id' => $log->id]);
    }
}
