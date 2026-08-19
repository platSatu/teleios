<?php

namespace App\Jobs;

use App\Exceptions\PackageLimitExceededException;
use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\User;
use App\Models\WaMessageSchedule;
use App\Models\WaMessageScheduleLog;
use App\Models\WaMessageScheduleStep;
use App\Services\Chat\BroadcastOptOutService;
use App\Services\Chat\BroadcastThrottleService;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
 * "group:...", "user:<uuid>" — see WaMessageSchedule::recipientKeys()).
 *
 * The Go backend only ever exposes two real send primitives — plain text
 * (WaInboxService::SendMessage) and a file attachment
 * (WaMediaService::SendMedia, used for image/document, same as the Inbox
 * paperclip button and a WA Template's own attachment). There's no native
 * WhatsApp location-pin or interactive-buttons message type built on the
 * Go side (see WaMessageTemplate::composedMessage()'s docblock for the
 * same limitation), so:
 *
 *   - category 'text'              → sent as plain text.
 *   - category 'location'          → composed as plain text too (name +
 *     link, same shape as WaMessageTemplate::composedMessage()) since
 *     that's literally all a manual 'location' entry stores — there's no
 *     lat/lng column here to build a real pin from.
 *   - category 'image'/'document'  → sent as a file attachment via
 *     sendStoredMedia(), using the schedule's own attachment_* columns
 *     (same path a WA Template's attachment already went through).
 *
 * The one combination that's still genuinely unsupported is image/
 * document on a *drip step* — WaMessageScheduleStep has no attachment
 * columns of its own (only a plain `message` textarea, see the create/
 * edit form), so that's marked failed with an explanatory reason instead
 * of silently never firing.
 *
 * Two anti-ban guards run here, right before anything actually goes out
 * over WhatsApp: an opt-out check (App\Services\Chat\
 * BroadcastOptOutService — never message a number that replied STOP)
 * and a hard per-device sends-per-minute ceiling (App\Services\Chat\
 * BroadcastThrottleService — on top of, not instead of, the randomized
 * dispatch-time stagger in App\Console\Commands\
 * DispatchDueWaMessageSchedules).
 */
class SendScheduledWaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    /** Give a flaky/reconnecting device a bit of room between retries. */
    public array $backoff = [30, 120, 300];

    /**
     * How many times this job may re-dispatch ITSELF because
     * App\Services\Chat\BroadcastThrottleService had no free send slot
     * for this device — deliberately tracked and bounded separately from
     * $tries/$backoff above. Being rate-limited isn't a failure (nothing
     * is broken, the device is just busy), so it must never eat into the
     * same retry budget genuine send errors use, and must never spin
     * forever either — see the throttle check in handle() below.
     */
    private const MAX_THROTTLE_REDISPATCHES = 30;

    /**
     * How long to wait before retrying a send that failed only because
     * the device had no live WhatsApp session at that instant (see
     * App\Services\Chat\InboxService::isDeviceDisconnected()) — checked
     * again fresh on every retry, so this recovers automatically the
     * moment the device reconnects instead of waiting out the full grace
     * period below.
     */
    private const DEVICE_OFFLINE_RETRY_SECONDS = 60;

    /**
     * How many times this job may re-dispatch ITSELF because the device
     * was offline, before finally giving up — tracked separately from
     * both $tries/$backoff and MAX_THROTTLE_REDISPATCHES above, for the
     * same reason those are separate from each other: being offline
     * isn't a send failure the normal retry budget was sized for, and
     * must never spin forever either.
     *
     * At DEVICE_OFFLINE_RETRY_SECONDS = 60s, 30 redispatches is a 30
     * minute grace period — long enough to cover a QR re-scan, a device
     * reboot, or a brief hosting blip, without a broadcast's already-
     * dispatched jobs (which, for a large recipient list, is every
     * recipient staggered out over the run — see
     * App\Console\Commands\DispatchDueWaMessageSchedules) all silently
     * piling up as permanently 'failed' within the ~7.5 minutes a plain
     * $tries=3/backoff=[30,120,300] would have allowed. Past that,
     * something is genuinely wrong (not just "still reconnecting"), so
     * this stops pausing and reports it instead of retrying forever.
     */
    private const MAX_DEVICE_OFFLINE_REDISPATCHES = 30;

    public function __construct(
        protected string $scheduleId,
        protected string $recipientKey,
        protected string $sendDate,
        protected int $stepOrder = 0,
        protected int $throttleAttempts = 0,
        protected int $deviceOfflineAttempts = 0,
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

    public function handle(SystemJwtService $jwtService, InboxService $inbox, BroadcastOptOutService $optOuts, BroadcastThrottleService $throttle, PackageLimitService $packageLimits): void
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

        // Billing guard: checked as early as possible, before any other
        // work (opt-out lookup, content resolution, throttle check, ...)
        // — see PackageLimitService::requireActivePackage()'s docblock
        // for why this can't just rely on the reserve() call further
        // down (which deliberately fails OPEN when there's no active
        // package) or on App\Http\Middleware\EnsureActivePackage (which
        // never runs for this job at all, since it's dispatched by a
        // scheduled command, not an HTTP request). Recorded as
        // STATUS_SKIPPED, not markFailed()'s 'failed' — same distinction
        // BroadcastOptOutService's guard below makes: this isn't a
        // broken send, it's a send that was never supposed to happen.
        if ($schedule->company) {
            try {
                $packageLimits->requireActivePackage($schedule->company);
            } catch (PackageLimitExceededException $e) {
                $log->forceFill([
                    'status' => WaMessageScheduleLog::STATUS_SKIPPED,
                    'error' => $e->getMessage(),
                    'attempts' => $log->attempts + 1,
                ])->save();

                return;
            }
        }

        // Anti-ban/compliance guard: never broadcast to a number that has
        // opted out (see App\Services\Chat\BroadcastOptOutService), even
        // if it's still sitting in the schedule's recipient list — a
        // customer's STOP reply must take effect immediately for every
        // future send, not just the schedule they happened to reply to.
        // Deliberately checked before content resolution/attachment
        // validation below: an opted-out recipient shouldn't get flagged
        // over an unrelated content problem it will never actually hit.
        // Not applicable to 'group' recipients — a WhatsApp group isn't
        // an individual who can opt out.
        $recipientType = $log->recipientType();
        $recipientValue = $log->recipientValue();

        if (in_array($recipientType, ['phone', 'user'], true)) {
            $recipientPhone = $this->resolveRecipientPhone($recipientType, $recipientValue);

            if ($recipientPhone && $schedule->company_id && $optOuts->isOptedOut($schedule->company_id, $recipientPhone)) {
                $log->forceFill([
                    'status' => WaMessageScheduleLog::STATUS_SKIPPED,
                    'error' => 'Nomor ini telah berhenti berlangganan (opt-out) dan dilewati demi mencegah pelaporan spam.',
                    'attempts' => $log->attempts + 1,
                ])->save();

                return;
            }
        }

        // Package quota guard: a company on a package that caps
        // "broadcast_send" (see App\Services\PackageLimitService,
        // App\Models\LimitMetric) is checked here, cheaply and
        // non-authoritatively, before doing any of the more expensive
        // content/attachment/JID resolution below — a company that's
        // visibly out of quota shouldn't pay for that work. This is
        // deliberately just a heads-up early exit: the REAL, concurrency
        // -safe check-and-consume happens once via reserve() right
        // before the network send further down (see the comment there
        // for why a separate check-then-consume pair isn't safe enough
        // for something this job runs highly concurrently).
        if ($schedule->company && $packageLimits->remaining($schedule->company, 'broadcast_send') === 0) {
            $log->forceFill([
                'status' => WaMessageScheduleLog::STATUS_SKIPPED,
                'error' => 'Kuota pengiriman broadcast paket Anda untuk periode ini sudah habis. Beli/upgrade paket untuk melanjutkan.',
                'attempts' => $log->attempts + 1,
            ])->save();

            return;
        }

        $content = $this->resolveContent($schedule);

        if ($content === null) {
            $this->markFailed($log, $this->contentFailureReason($schedule));

            return;
        }

        // A drip step with category 'image'/'document' has no attachment
        // of its own to send (see the class docblock) — caught here,
        // before touching the network, rather than letting sendStoredMedia()
        // below blow up on a null path.
        if (in_array($content['category'], ['image', 'document'], true) && ! $content['attachmentPath']) {
            $this->markFailed($log, "Lampiran {$content['category']} untuk langkah drip belum didukung — hanya tersedia untuk jenis pengiriman Sekali/Berulang.");

            return;
        }

        // The file was uploaded and the path is set, but it's gone from
        // disk (e.g. storage was cleared/moved outside the app) — fail
        // explicitly instead of silently falling through to sending an
        // empty text message (image/document schedules leave `message`
        // unused, see the migration docblock, so the "body" would be
        // blank).
        if ($content['attachmentPath'] && ! Storage::disk('public')->exists($content['attachmentPath'])) {
            $this->markFailed($log, "File lampiran {$content['category']} tidak ditemukan di server. Buka kembali jadwal ini dan unggah ulang filenya.");

            return;
        }

        $chatJid = $this->resolveChatJid($recipientType, $recipientValue);

        if (! $chatJid) {
            $this->markFailed($log, $this->unresolvedReason($recipientType));

            return;
        }

        // Anti-ban guard #2: a hard per-device ceiling on sends-per-minute
        // (see App\Services\Chat\BroadcastThrottleService), enforced here
        // — right before the network call, once every other reason this
        // send might not happen has already been ruled out — so a slot
        // is never wasted "reserving" a send that was going to be
        // skipped/failed anyway. Not a failure: if the device is simply
        // busy, this job re-dispatches itself with a delay instead of
        // touching $log at all, up to MAX_THROTTLE_REDISPATCHES times
        // before finally giving up — see the constant's docblock for why
        // this is tracked separately from $tries/$backoff.
        if (! $throttle->attempt($schedule->device_id, $schedule->company_id)) {
            if ($this->throttleAttempts >= self::MAX_THROTTLE_REDISPATCHES) {
                $this->markFailed($log, 'Perangkat pengirim terlalu sibuk (batas kirim per menit tercapai berulang kali) — pesan tidak jadi terkirim. Coba kirim ulang nanti.');

                return;
            }

            $delaySeconds = max(5, $throttle->availableInSeconds($schedule->device_id));

            Log::info('SendScheduledWaMessage: device at broadcast rate limit, re-queueing', [
                'schedule_id' => $schedule->id,
                'device_id' => $schedule->device_id,
                'recipient_key' => $this->recipientKey,
                'throttle_attempt' => $this->throttleAttempts + 1,
                'retry_in_seconds' => $delaySeconds,
            ]);

            self::dispatch($this->scheduleId, $this->recipientKey, $this->sendDate, $this->stepOrder, $this->throttleAttempts + 1)
                ->delay(now()->addSeconds($delaySeconds));

            return;
        }

        // The real, concurrency-safe quota check-and-consume: atomic
        // (locked, single-transaction) so many of this exact job running
        // across several queue workers for the same company at once
        // can't all read "still room" before any of them writes back —
        // see App\Services\PackageLimitService::reserve()'s docblock.
        // Placed here, right before the network call, once every other
        // reason this send might not happen has already been ruled out
        // (same reasoning as the throttle guard above it) — a slot is
        // never reserved for a send that was going to be skipped/failed
        // anyway. If the send below then fails, release() gives the
        // reservation back in the catch block so a retried/failed
        // attempt never permanently burns quota it never actually used.
        if ($schedule->company) {
            try {
                $packageLimits->reserve($schedule->company, 'broadcast_send');
            } catch (PackageLimitExceededException $e) {
                $log->forceFill([
                    'status' => WaMessageScheduleLog::STATUS_SKIPPED,
                    'error' => $e->getMessage(),
                    'attempts' => $log->attempts + 1,
                ])->save();

                return;
            }
        }

        try {
            $token = $jwtService->mintFor($owner);

            // Three ways this can go out:
            //   1. A template with a stored image/document → sent as
            //      media with the composed text as caption (previously
            //      the attachment was never forwarded at all, only the
            //      composed text).
            //   2. A manual (non-template) 'image'/'document' schedule →
            //      same media send, using the schedule's own
            //      attachment_* columns instead of a template's.
            //   3. Anything else ('text', or 'location' composed down to
            //      text above) → plain text.
            $template = $content['template'];
            $body = $content['body'];

            if ($template && $template->attachment_path && Storage::disk('public')->exists($template->attachment_path)) {
                $sent = $inbox->sendStoredMedia(
                    $token,
                    $schedule->device_id,
                    $chatJid,
                    Storage::disk('public')->path($template->attachment_path),
                    $template->attachment_original_name ?: basename($template->attachment_path),
                    $this->realMimeType($template->attachment_path, $template->attachment_type),
                    $body
                );
            } elseif ($content['attachmentPath'] && Storage::disk('public')->exists($content['attachmentPath'])) {
                $sent = $inbox->sendStoredMedia(
                    $token,
                    $schedule->device_id,
                    $chatJid,
                    Storage::disk('public')->path($content['attachmentPath']),
                    $content['attachmentName'] ?: basename($content['attachmentPath']),
                    $this->realMimeType($content['attachmentPath'], $content['attachmentType']),
                    $body
                );
            } else {
                $sent = $inbox->send($token, $schedule->device_id, $chatJid, $body);
            }

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
            // The reservation made above didn't correspond to a real
            // send after all — give it back before retrying/failing, so
            // quota is only ever permanently spent on a message that
            // genuinely went out.
            if ($schedule->company) {
                $packageLimits->release($schedule->company, 'broadcast_send');
            }

            // Auto-pause/resume: the device simply being offline right
            // now isn't treated as a send failure — see
            // InboxService::isDeviceDisconnected()'s docblock and
            // MAX_DEVICE_OFFLINE_REDISPATCHES above for the full
            // reasoning. Checked before touching $log at all (same as
            // the throttle guard earlier in this method), so a device
            // that's mid-reconnect never eats into $tries/backoff nor
            // shows up as a failed attempt in the history page — it
            // just quietly retries until the device is back, up to the
            // grace period.
            if (InboxService::isDeviceDisconnected($e)) {
                if ($this->deviceOfflineAttempts >= self::MAX_DEVICE_OFFLINE_REDISPATCHES) {
                    $this->markFailed($log, 'Device pengirim terputus terlalu lama (lebih dari 30 menit) — pengiriman dibatalkan. Sambungkan ulang device di menu Connect Device, lalu kirim ulang jadwal ini.');

                    return;
                }

                Log::info('SendScheduledWaMessage: device offline, pausing and re-queueing', [
                    'schedule_id' => $schedule->id,
                    'device_id' => $schedule->device_id,
                    'recipient_key' => $this->recipientKey,
                    'offline_attempt' => $this->deviceOfflineAttempts + 1,
                    'retry_in_seconds' => self::DEVICE_OFFLINE_RETRY_SECONDS,
                ]);

                self::dispatch(
                    $this->scheduleId,
                    $this->recipientKey,
                    $this->sendDate,
                    $this->stepOrder,
                    $this->throttleAttempts,
                    $this->deviceOfflineAttempts + 1
                )->delay(now()->addSeconds(self::DEVICE_OFFLINE_RETRY_SECONDS));

                return;
            }

            Log::warning('SendScheduledWaMessage: send failed', [
                'schedule_id' => $schedule->id,
                'recipient_key' => $this->recipientKey,
                'send_date' => $this->sendDate,
                'step_order' => $this->stepOrder,
                'error' => $e->getMessage(),
            ]);

            $log->forceFill([
                'attempts' => $log->attempts + 1,
                // Human-readable reason, not the raw "Golang inbox
                // request to ... failed: {...}" exception text — that
                // used to leak straight into History Pengiriman's
                // Keterangan column verbatim.
                'error' => InboxService::describeSendFailure($e),
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
     * WaMessageTemplate::attachment_type / WaMessageSchedule::
     * attachment_type only ever store the coarse form category
     * ('image'/'document'), never a real MIME type — but the Go
     * backend's classifyMime() (wa-media-service.go) does
     * strings.HasPrefix(mimeType, "image/") to decide whether to send a
     * real ImageMessage vs. falling back to a generic DocumentMessage.
     * Passing the bare word "image" straight through fails that prefix
     * check, so every 'image' category attachment was silently going out
     * as a plain document instead of an actual photo. Detecting the real
     * MIME type from the file on disk (falling back to the stored
     * category only if detection somehow fails) is what actually fixes
     * that, without needing any Go-side change.
     */
    protected function realMimeType(string $storedPath, ?string $fallback): ?string
    {
        try {
            $detected = Storage::disk('public')->mimeType($storedPath);
        } catch (Throwable $e) {
            $detected = false;
        }

        return $detected ?: $fallback;
    }

    /**
     * Content to send, pulled from the matching WaMessageScheduleStep for
     * a 'drip' schedule (stepOrder > 0), or from the schedule itself
     * otherwise — either way resolved live at send time (not snapshotted
     * when the schedule/step was saved), so editing a template afterwards
     * changes every future occurrence that still points at it. Null means
     * there's genuinely nothing to send (template deleted/empty, manual
     * text/location left blank, image/document with no file uploaded, or
     * the step itself is gone) — handle() reports that via
     * contentFailureReason() instead of attempting a send.
     *
     * @return array{category: string, body: string, template: ?\App\Models\WaMessageTemplate, attachmentPath: ?string, attachmentName: ?string, attachmentType: ?string}|null
     */
    protected function resolveContent(WaMessageSchedule $schedule): ?array
    {
        $empty = fn (string $category, string $body = '') => [
            'category' => $category,
            'body' => $body,
            'template' => null,
            'attachmentPath' => null,
            'attachmentName' => null,
            'attachmentType' => null,
        ];

        if ($this->stepOrder > 0) {
            $step = WaMessageScheduleStep::where('wa_message_schedule_id', $schedule->id)
                ->where('sequence_order', $this->stepOrder)
                ->first();

            if (! $step) {
                return null;
            }

            if ($step->use_template) {
                $template = $step->waMessageTemplate;
                $body = $template?->composedMessage();

                return $body ? array_merge($empty('text', $body), ['template' => $template]) : null;
            }

            $category = $step->category_schedule ?: 'text';

            // WaMessageScheduleStep has no attachment_path/link columns
            // of its own (only a plain `message` textarea — see the
            // class docblock and the create/edit form's step rows), so
            // 'location' here is just the raw message as text (no
            // separate link to append), and 'image'/'document' has
            // nothing to attach. handle() turns the latter into an
            // explicit "not supported at step level" failure rather than
            // this returning null, which would misleadingly read as "isi
            // pesan kosong" when the real issue is the category itself.
            if (in_array($category, ['image', 'document'], true)) {
                return $empty($category);
            }

            return $step->message ? $empty($category, $step->message) : null;
        }

        if ($schedule->use_template) {
            $template = $schedule->waMessageTemplate;
            $body = $template?->composedMessage();

            return $body ? array_merge($empty('text', $body), ['template' => $template]) : null;
        }

        $category = $schedule->category_schedule ?: 'text';

        if ($category === 'location') {
            // Same "name + link" text composition WaMessageTemplate::
            // composedMessage() uses for its own text_link content —
            // there's no lat/lng column here to build a real WhatsApp
            // location-pin message from (see the class docblock).
            $body = trim(collect([$schedule->message, $schedule->link])->filter()->implode("\n"));

            return $body !== '' ? $empty('location', $body) : null;
        }

        if (in_array($category, ['image', 'document'], true)) {
            if (! $schedule->attachment_path) {
                return null;
            }

            return [
                'category' => $category,
                // `message` is unused for these categories on the form
                // (see the attachment-columns migration docblock) — sent
                // as an empty caption rather than treated as "no
                // content", since the file itself is the content.
                'body' => (string) $schedule->message,
                'template' => null,
                'attachmentPath' => $schedule->attachment_path,
                'attachmentName' => $schedule->attachment_original_name,
                'attachmentType' => $schedule->attachment_type,
            ];
        }

        return $schedule->message ? $empty('text', $schedule->message) : null;
    }

    protected function contentFailureReason(WaMessageSchedule $schedule): string
    {
        if ($this->stepOrder > 0) {
            return 'Langkah pesan ini sudah dihapus, template-nya sudah dihapus, atau isi pesannya kosong.';
        }

        if ($schedule->use_template) {
            return 'Template WA yang dipilih sudah dihapus atau kosong.';
        }

        return match ($schedule->category_schedule) {
            'location' => 'Nama lokasi dan link lokasi masih kosong.',
            'image', 'document' => 'Belum ada file yang diunggah untuk lampiran ini.',
            default => 'Isi pesan kosong.',
        };
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

    /**
     * Raw phone digits for the App\Services\Chat\BroadcastOptOutService
     * check in handle() above — deliberately separate from
     * resolveChatJid()/resolveUserJid(), which return a WhatsApp JID
     * (phone digits wrapped in "@s.whatsapp.net"), not a bare phone
     * number. Only meaningful for 'phone'/'user' recipients; callers
     * don't invoke this for 'group'.
     */
    protected function resolveRecipientPhone(string $type, string $value): ?string
    {
        return match ($type) {
            'phone' => $value !== '' ? $value : null,
            'user' => User::find($value)?->handphone,
            default => null,
        };
    }
}
