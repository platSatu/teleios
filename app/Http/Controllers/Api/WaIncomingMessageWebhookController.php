<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAutoReplyMessage;
use App\Models\WaMessageAutoReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receives "a WhatsApp message just arrived" from the Go backend (see
 * g_backend's WaInboxService.notifyIncomingMessageWebhook) and drives
 * "Auto Reply (Kata Kunci)": every active WaMessageAutoReply row for the
 * message's device_id is checked against the message body, and the
 * first one that matches gets its reply_message sent back — see
 * App\Jobs\SendAutoReplyMessage for the actual send.
 *
 * Only the FIRST matching rule fires per incoming message (ordered
 * oldest-created first) rather than every match, so one message can't
 * make the bot fire off a burst of replies if a company configured
 * several overlapping keywords.
 *
 * If NOTHING matches, this now falls back to the device's `is_default`
 * rule (if one's configured) instead of silently doing nothing — that's
 * what makes a numbered menu reachable at all: someone who messages
 * with no idea what to type gets the default rule's menu text ("1.
 * Jadwal, 2. Pembayaran, 3. Daftar User, ketik salah satu nomor"), and
 * "1"/"2"/"3" are then just ordinary keyword rules a level down.
 */
class WaIncomingMessageWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Logged unconditionally, before validation even runs, so a
        // request that never makes it past validation (bad payload,
        // wrong field names from a Go-side change, etc.) still leaves a
        // trace — that case previously left zero evidence anywhere.
        Log::info('wa-auto-reply: webhook received', ['payload' => $request->all()]);

        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'user_id' => ['nullable', 'string'],
            'chat_jid' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'body' => ['required', 'string'],
            'sent_at' => ['nullable', 'date'],
        ]);

        // Go's own network retry (or the outer HTTP client timing out
        // after the request already succeeded) could deliver the same
        // message_id twice — a short, cheap lock keyed on it makes sure
        // that never sends two replies for one incoming message. TTL
        // only needs to outlive how long a retry could plausibly take.
        $lockKey = 'wa-auto-reply:message:'.$validated['message_id'];

        if (! Cache::add($lockKey, true, now()->addMinutes(10))) {
            Log::info('wa-auto-reply: duplicate message_id, ignored', ['message_id' => $validated['message_id']]);

            return response()->json(['status' => 'duplicate, ignored']);
        }

        $activeRules = WaMessageAutoReply::query()
            ->where('device_id', $validated['device_id'])
            ->where('status', 'active')
            ->oldest()
            ->get();

        Log::info('wa-auto-reply: checking rules for device', [
            'device_id' => $validated['device_id'],
            'body' => $validated['body'],
            'active_rule_count' => $activeRules->count(),
            'active_rule_keywords' => $activeRules->pluck('keyword', 'id'),
        ]);

        $rule = $activeRules->first(fn (WaMessageAutoReply $rule) => $rule->matches($validated['body']));
        $matchedDefault = false;

        if (! $rule) {
            $rule = $activeRules->firstWhere('is_default', true);
            $matchedDefault = (bool) $rule;
        }

        if (! $rule) {
            Log::info('wa-auto-reply: no rule matched and no default configured', [
                'device_id' => $validated['device_id'],
                'body' => $validated['body'],
            ]);

            return response()->json(['status' => 'no match']);
        }

        Log::info($matchedDefault ? 'wa-auto-reply: no keyword matched, falling back to default rule' : 'wa-auto-reply: rule matched, dispatching reply job', [
            'rule_id' => $rule->id,
            'keyword' => $rule->keyword,
            'is_default' => $matchedDefault,
            'chat_jid' => $validated['chat_jid'],
        ]);

        SendAutoReplyMessage::dispatch($rule->id, $validated['chat_jid']);

        return response()->json([
            'status' => $matchedDefault ? 'matched (default)' : 'matched',
            'rule_id' => $rule->id,
        ]);
    }
}
