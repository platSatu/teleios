<?php

namespace App\Jobs;

use App\Exceptions\PackageLimitExceededException;
use App\Models\WaMessageAutoReply;
use App\Services\Chat\AutoReplyTagResolver;
use App\Services\Chat\BroadcastThrottleService;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one "Auto Reply (Kata Kunci)" reply — dispatched by
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController the
 * moment a matching keyword is found in an incoming message. Unlike
 * SendScheduledWaMessage, there's no "already sent" row to guard here —
 * each dispatch is for a distinct incoming
 * message (deduped by message_id at the webhook, before this job even
 * exists), so this job runs unconditionally once dispatched.
 *
 * Shares its device with whatever broadcast/schedule traffic
 * (App\Jobs\SendScheduledWaMessage) is also going out on it, so it goes
 * through the exact same per-device sends-per-minute ceiling
 * (App\Services\Chat\BroadcastThrottleService) that job does — without
 * this, a burst of keyword auto-replies could push a device's real
 * outbound rate past the ceiling broadcast was throttled down to,
 * silently defeating the whole point of that guard.
 */
class SendAutoReplyMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    /**
     * Same reasoning as App\Jobs\SendScheduledWaMessage's identical
     * constant: how many times this job may re-dispatch ITSELF because
     * BroadcastThrottleService had no free send slot for this device.
     * Tracked separately from $tries/$backoff — being rate-limited isn't
     * a failure, and must never eat into the retry budget genuine send
     * errors use, nor spin forever.
     */
    private const MAX_THROTTLE_REDISPATCHES = 30;

    public function __construct(
        protected string $autoReplyId,
        protected string $chatJid,
        protected int $throttleAttempts = 0,
    ) {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, AutoReplyTagResolver $tagResolver, BroadcastThrottleService $throttle, PackageLimitService $packageLimits): void
    {
        $rule = WaMessageAutoReply::with('company.user')->find($this->autoReplyId);

        // Rule could've been deleted/deactivated between the webhook
        // matching it and this job actually running — re-check status
        // rather than trusting the snapshot from when it was dispatched.
        if (! $rule || $rule->status !== 'active') {
            return;
        }

        $owner = $rule->company?->user;

        if (! $owner) {
            $this->markFailed($rule, 'Perusahaan pemilik rule ini tidak memiliki user pemilik yang valid.');

            return;
        }

        // Billing guard — see App\Services\PackageLimitService::
        // requireActivePackage()'s docblock for why this is a separate
        // check from anything broadcast-quota-related: this job runs
        // outside any HTTP request (dispatched from a webhook handler,
        // not a page load), so App\Http\Middleware\EnsureActivePackage
        // never gets a chance to block a reply going out for a company
        // whose package has since expired.
        if ($rule->company) {
            try {
                $packageLimits->requireActivePackage($rule->company);
            } catch (PackageLimitExceededException $e) {
                $this->markFailed($rule, $e->getMessage());

                return;
            }
        }

        // Anti-ban guard: same per-device ceiling App\Jobs\
        // SendScheduledWaMessage enforces, checked right before the send
        // — see that job's handle() for the original pattern this
        // mirrors. Not a failure: if the device is simply busy, this job
        // re-dispatches itself with a delay instead of touching $rule at
        // all, up to MAX_THROTTLE_REDISPATCHES times before giving up.
        if (! $throttle->attempt($rule->device_id, $rule->company_id)) {
            if ($this->throttleAttempts >= self::MAX_THROTTLE_REDISPATCHES) {
                $this->markFailed($rule, 'Perangkat pengirim terlalu sibuk (batas kirim per menit tercapai berulang kali) — balasan otomatis tidak jadi terkirim.');

                return;
            }

            $delaySeconds = max(5, $throttle->availableInSeconds($rule->device_id));

            Log::info('SendAutoReplyMessage: device at broadcast rate limit, re-queueing', [
                'auto_reply_id' => $rule->id,
                'device_id' => $rule->device_id,
                'chat_jid' => $this->chatJid,
                'throttle_attempt' => $this->throttleAttempts + 1,
                'retry_in_seconds' => $delaySeconds,
            ]);

            self::dispatch($this->autoReplyId, $this->chatJid, $this->throttleAttempts + 1)
                ->delay(now()->addSeconds($delaySeconds));

            return;
        }

        try {
            $token = $jwtService->mintFor($owner);

            // Resolved fresh on every send — a {{jadwal_aktif}}-style tag
            // always reflects current data, not whatever it looked like
            // when this rule was last saved. See AutoReplyTagResolver's
            // docblock for the full tag list.
            $body = $tagResolver->resolve($rule->reply_message, $rule->company);

            $inbox->send($token, $rule->device_id, $this->chatJid, $body);

            $rule->forceFill([
                'trigger_count' => $rule->trigger_count + 1,
                'last_triggered_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendAutoReplyMessage: send failed', [
                'auto_reply_id' => $rule->id,
                'device_id' => $rule->device_id,
                'chat_jid' => $this->chatJid,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($rule, $e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        WaMessageAutoReply::whereKey($this->autoReplyId)->update([
            'last_error' => $e->getMessage(),
        ]);
    }

    protected function markFailed(WaMessageAutoReply $rule, string $reason): void
    {
        $rule->forceFill(['last_error' => $reason])->save();
    }
}
