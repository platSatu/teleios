<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\User;
use App\Models\WaMessageSchedule;
use App\Models\WaMessageScheduleLog;
use App\Models\WaMessageScheduleStep;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one due (schedule, recipient, day, step) combination — dispatched
 * by App\Console\Commands\DispatchDueWaMessageSchedules once that
 * combination's App\Models\WaMessageScheduleLog row has been claimed as
 * 'pending'. Covers all 3 WaMessageSchedule types (this also replaces
 * the old, now-retired SendMessageSequenceStep):
 *
 *   - $stepOrder = 0: 'once'/'recurring' schedule — content comes from
 *     the schedule's own message/category_schedule or use_template +
 *     wa_message_template_id.
 *   - $stepOrder > 0: 'drip' schedule — content comes from the matching
 *     App\Models\WaMessageScheduleStep instead (same
 *     manual-vs-template shape, just one level down).
 *
 * Either way the target is resolved from the recipient key ("phone:...",
 * "group:...", "user:<uuid>" — see WaMessageSchedule::recipientKeys()),
 * and only category 'text' actually sends: there's no location/image/
 * document/button send endpoint on the Go backend yet, so anything else
 * is marked failed with an explanatory reason instead of silently never
 * firing.
 */
class SendScheduledWaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    /** Give a flaky/reconnecting device a bit of room between retries. */
    public array $backoff = [30, 120, 300];

    public function __construct(
        protected string $scheduleId,
        protected string $recipientKey,
        protected string $sendDate,
        protected int $stepOrder = 0,
    ) {
    }

    /**
     * Keyed on schedule + recipient + day + step — if the dispatcher
     * command somehow enqueues the same due combination twice, the
     * second job just waits instead of racing the first for the same
     * send.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->scheduleId}-{$this->recipientKey}-{$this->sendDate}-{$this->stepOrder}"))
                ->releaseAfter(120)
                ->expireAfter(180),
        ];
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox): void
    {
        $log = $this->findLog();

        // Already sent (a previous try of this same job succeeded right
        // before a retry got scheduled), or the log row is gone entirely
        // (the schedule was edited/deleted out from under this job) —
        // either way, there's nothing left to do.
        if (! $log || $log->status === 'sent') {
            return;
        }

        $schedule = WaMessageSchedule::with(['company.user', 'waMessageTemplate'])->find($this->scheduleId);

        if (! $schedule) {
            $this->markFailed($log, 'Jadwal sudah dihapus.');

            return;
        }

        $owner = $schedule->company?->user;

        if (! $owner) {
            $this->markFailed($log, 'Perusahaan pemilik jadwal ini tidak memiliki user pemilik yang valid.');

            return;
        }

        $content = $this->resolveContent($schedule);

        if ($content === null) {
            $this->markFailed($log, $this->contentFailureReason($schedule));

            return;
        }

        [$category, $body] = $content;

        if ($category !== 'text') {
            $this->markFailed($log, "Kategori '{$category}' belum didukung pengiriman otomatis (backend baru mendukung pengiriman teks).");

            return;
        }

        $recipientType = $log->recipientType();
        $recipientValue = $log->recipientValue();
        $chatJid = $this->resolveChatJid($recipientType, $recipientValue);

        if (! $chatJid) {
            $this->markFailed($log, $this->unresolvedReason($recipientType));

            return;
        }

        try {
            $token = $jwtService->mintFor($owner);

            $sent = $inbox->send($token, $schedule->device_id, $chatJid, $body);

            $log->forceFill([
                'status' => 'sent',
                // WhatsApp's own message id (g_backend's WaMessage.
                // MessageID) — captured so a later delivery/read receipt
                // (App\Http\Controllers\Api\WaMessageStatusWebhookController)
                // can find its way back to this exact log row.
                'message_id' => $sent['message_id'] ?? null,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendScheduledWaMessage: send failed', [
                'schedule_id' => $schedule->id,
                'recipient_key' => $this->recipientKey,
                'send_date' => $this->sendDate,
                'step_order' => $this->stepOrder,
                'error' => $e->getMessage(),
            ]);

            $log->forceFill([
                'attempts' => $log->attempts + 1,
                'error' => $e->getMessage(),
            ])->save();

            // Rethrow so Laravel's queue retry/backoff (tries/backoff
            // above) kicks in. The log's own status only flips to
            // 'failed' once retries are exhausted — see failed() below —
            // so a transient failure that succeeds on try 2 never shows
            // up as "Gagal" in the history page.
            throw $e;
        }
    }

    /**
     * Called once `tries` is exhausted — this is the only place a
     * genuinely final failure gets recorded as `status = 'failed'`
     * (every retry in between just updates attempts/error while status
     * stays 'pending', via the catch block in handle() above).
     */
    public function failed(Throwable $e): void
    {
        $this->findLog()?->forceFill([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ])->save();
    }

    protected function findLog(): ?WaMessageScheduleLog
    {
        return WaMessageScheduleLog::where('wa_message_schedule_id', $this->scheduleId)
            ->where('recipient_key', $this->recipientKey)
            ->where('send_date', $this->sendDate)
            ->where('step_order', $this->stepOrder)
            ->first();
    }

    protected function markFailed(WaMessageScheduleLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => 'failed',
            'attempts' => $log->attempts + 1,
            'error' => $reason,
        ])->save();
    }

    /**
     * [category, body] to send, pulled from the matching
     * WaMessageScheduleStep for a 'drip' schedule (stepOrder > 0), or
     * from the schedule itself otherwise — either way resolved live at
     * send time (not snapshotted when the schedule/step was saved), so
     * editing a template afterwards changes every future occurrence that
     * still points at it. Null means there's nothing to send (template
     * deleted/empty, manual message empty, or the step itself is gone).
     */
    protected function resolveContent(WaMessageSchedule $schedule): ?array
    {
        if ($this->stepOrder > 0) {
            $step = WaMessageScheduleStep::where('wa_message_schedule_id', $schedule->id)
                ->where('sequence_order', $this->stepOrder)
                ->first();

            if (! $step) {
                return null;
            }

            $body = $step->use_template ? $step->waMessageTemplate?->composedMessage() : $step->message;
            $category = $step->use_template ? 'text' : ($step->category_schedule ?: 'text');

            return $body ? [$category, $body] : null;
        }

        $body = $schedule->use_template ? $schedule->waMessageTemplate?->composedMessage() : $schedule->message;
        $category = $schedule->use_template ? 'text' : ($schedule->category_schedule ?: 'text');

        return $body ? [$category, $body] : null;
    }

    protected function contentFailureReason(WaMessageSchedule $schedule): string
    {
        if ($this->stepOrder > 0) {
            return 'Langkah pesan ini sudah dihapus, template-nya sudah dihapus, atau isi pesannya kosong.';
        }

        return $schedule->use_template
            ? 'Template WA yang dipilih sudah dihapus atau kosong.'
            : 'Isi pesan kosong.';
    }

    protected function resolveChatJid(string $type, string $value): ?string
    {
        return match ($type) {
            'group' => $value !== '' ? $value : null,
            'phone' => $this->toIndividualJid($value),
            'user' => $this->resolveUserJid($value),
            default => null,
        };
    }

    /**
     * A "user" recipient is a company member picked from tab 3 of the
     * form — resolved to their own users.handphone at send time (rather
     * than snapshotting a phone number into the schedule up front), so
     * if their number changes later every future occurrence sends to the
     * current one.
     */
    protected function resolveUserJid(string $userId): ?string
    {
        $handphone = User::find($userId)?->handphone;

        return $handphone ? $this->toIndividualJid($handphone) : null;
    }

    protected function unresolvedReason(string $type): string
    {
        return match ($type) {
            'group' => 'Group JID kosong.',
            'phone' => 'Nomor WhatsApp tujuan kosong atau tidak valid.',
            'user' => 'User tidak ditemukan atau belum mengatur nomor WhatsApp (handphone) di profilnya.',
            default => 'Tujuan pengiriman tidak dikenali.',
        };
    }
}
