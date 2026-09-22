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
 * delivered_count/read_count/recipient_total (diskusi 22 September 2026)
 * -- WhatsApp mengirim tanda-terima TERPISAH per anggota grup, dan
 * g_backend sekarang melacak tiap anggota secara terpisah juga (lihat
 * WaMessageReceipt di sisi Go) alih-alih menyamaratakan semuanya jadi satu
 * `status` -- itu sebabnya sebelum ini, "read" ke grup tidak pernah bisa
 * lebih dari 1 walau anggotanya banyak yang baca. Setiap panggilan webhook
 * ini sekarang membawa hitungan TERBARU (bukan delta), jadi selalu di-
 * MAX-kan terhadap yang sudah tersimpan, bukan ditambahkan begitu saja --
 * lebih dari satu request bisa datang untuk pesan yang sama dari anggota
 * grup yang berbeda-beda.
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
            'delivered_count' => ['nullable', 'integer', 'min:0'],
            'read_count' => ['nullable', 'integer', 'min:0'],
            // 0 dari g_backend berarti "ukuran grup belum diketahui",
            // BUKAN "0 penerima" -- lihat migration
            // add_receipt_counts_to_wa_message_schedule_logs.php.
            'recipient_total' => ['nullable', 'integer', 'min:0'],
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

        $updates = [
            // Selalu di-MAX-kan, TIDAK digerbang oleh $incomingRank <=
            // $currentRank di bawah -- receipt dari anggota grup KEDUA,
            // KETIGA, dst tetap harus menaikkan hitungan walau `status`
            // pesan (skala pesan, bukan per-anggota) sudah mentok di
            // rank tertinggi sejak anggota pertama.
            'delivered_count' => max($log->delivered_count, $validated['delivered_count'] ?? 0),
            'read_count' => max($log->read_count, $validated['read_count'] ?? 0),
        ];

        if (! empty($validated['recipient_total'])) {
            $updates['recipient_total'] = max($log->recipient_total ?? 0, $validated['recipient_total']);
        }

        if ($incomingRank > $currentRank) {
            // Never move backwards — same rule g_backend's own
            // messageStatusRank() applies to wa_messages.status, kept
            // consistent on this side too (e.g. a delayed 'delivered'
            // landing after 'read' already did).
            $updates['status'] = $incomingStatus;
        }

        $log->forceFill($updates)->save();

        Log::info('wa-message-status: log updated', [
            'log_id' => $log->id,
            'message_id' => $validated['message_id'],
            'updates' => $updates,
        ]);

        return response()->json(['status' => 'updated', 'log_id' => $log->id]);
    }
}
