<?php

namespace App\Jobs;

use App\Models\WaMessageAutoReply;
use App\Services\Chat\AutoReplyTagResolver;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
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
 */
class SendAutoReplyMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(protected string $autoReplyId, protected string $chatJid)
    {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, AutoReplyTagResolver $tagResolver): void
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
