<?php

namespace App\Jobs;

use App\Models\Company;
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
 * Sends the one-line acknowledgement reply after a customer texts a
 * STOP/START-style keyword — dispatched by
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController::
 * tryOptOutKeyword(). Queued (not sent inline from the webhook) for the
 * same reason App\Jobs\SendAutoReplyMessage is: the webhook must respond
 * to the Go backend quickly regardless of how long the actual WhatsApp
 * round-trip takes.
 *
 * Deliberately bypasses App\Services\Chat\BroadcastThrottleService — this
 * is a single reply directly answering something the customer just sent,
 * not a broadcast, so it isn't the kind of automated volume the
 * throttle exists to cap.
 */
class SendOptOutConfirmationMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(
        protected string $companyId,
        protected string $deviceId,
        protected string $chatJid,
        protected string $body,
    ) {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox): void
    {
        $owner = Company::with('user')->find($this->companyId)?->user;

        if (! $owner) {
            Log::warning('SendOptOutConfirmationMessage: company has no valid owner user, reply not sent', [
                'company_id' => $this->companyId,
                'device_id' => $this->deviceId,
            ]);

            return;
        }

        try {
            $token = $jwtService->mintFor($owner);
            $inbox->send($token, $this->deviceId, $this->chatJid, $this->body);
        } catch (Throwable $e) {
            Log::warning('SendOptOutConfirmationMessage: send failed', [
                'company_id' => $this->companyId,
                'device_id' => $this->deviceId,
                'chat_jid' => $this->chatJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
