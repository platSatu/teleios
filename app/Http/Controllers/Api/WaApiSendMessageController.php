<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaApiKey;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ONE thing a third party can do with a WaApiKey token/secret right
 * now: send a WhatsApp message through that specific device — e.g. as a
 * notification channel from another system (an order placed, a ticket
 * updated, whatever the third party wants to alert someone about).
 *
 * Deliberately minimal — no read access to chat history, no device
 * management, nothing beyond "send". Every other Chat feature in this
 * app (auto-reply, scheduled messages, etc.) is reachable only by a
 * logged-in company member; this is the one exception, gated purely by
 * App\Http\Middleware\VerifyWaApiKey (see routes/api.php — no `auth`
 * middleware at all, since the caller isn't a user of this app).
 *
 * Sends through the SAME path every other outbound message in this app
 * uses (App\Jobs\SendAutoReplyMessage, App\Jobs\SendScheduledWaMessage):
 * mint a short-lived system JWT for the company owner (App\Services\
 * Chat\SystemJwtService), then App\Services\Chat\InboxService::send().
 * Go's own per-request AssertOwnership(userID, deviceID) still applies
 * normally — this can't be used to send through a device the key's
 * company doesn't actually own.
 */
class WaApiSendMessageController extends Controller
{
    public function send(Request $request, SystemJwtService $jwtService, InboxService $inbox): JsonResponse
    {
        /** @var WaApiKey $apiKey */
        $apiKey = $request->attributes->get('waApiKey');

        $validated = $request->validate([
            // Plain phone number (e.g. "6281234567890") OR a full WA JID
            // ("6281234567890@s.whatsapp.net" / "...@g.us" for a group) —
            // either is accepted, same flexibility InboxController gives
            // a logged-in user, so a third party doesn't need to know
            // this app's JID format just to send a DM.
            'to' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $chatJid = $this->normalizeJid($validated['to']);

        $owner = $apiKey->company?->user;

        if (! $owner) {
            return response()->json([
                'error' => 'Company pemilik API Key ini tidak memiliki user pemilik yang valid.',
            ], 500);
        }

        try {
            $token = $jwtService->mintFor($owner);
            $result = $inbox->send($token, $apiKey->device_id, $chatJid, $validated['message']);

            return response()->json([
                'status' => 'sent',
                'message' => $result,
            ]);
        } catch (Throwable $e) {
            Log::warning('WaApiSendMessageController: send failed', [
                'api_key_id' => $apiKey->id,
                'device_id' => $apiKey->device_id,
                'to' => $chatJid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Gagal mengirim pesan. Pastikan device masih terhubung.',
            ], 502);
        }
    }

    /**
     * A bare phone number (digits only, optionally with a leading '+')
     * becomes an individual JID; anything already containing '@' (a real
     * JID, individual or group) is passed through untouched.
     */
    private function normalizeJid(string $to): string
    {
        if (str_contains($to, '@')) {
            return $to;
        }

        $digits = preg_replace('/\D/', '', $to);

        return $digits.'@s.whatsapp.net';
    }
}
