<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAiBotReply;
use App\Jobs\SendAutoReplyMessage;
use App\Models\WaAiBot;
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
 * If no keyword rule matches, this falls back to the device's
 * `is_default` rule (if one's configured) — that's what makes a
 * numbered menu reachable at all: someone who messages with no idea
 * what to type gets the default rule's menu text ("1. Jadwal, 2.
 * Pembayaran, 3. Daftar User, ketik salah satu nomor"), and "1"/"2"/"3"
 * are then just ordinary keyword rules a level down.
 *
 * If THAT also doesn't exist, this falls back one level further to the
 * device's AI Bot config (App\Models\WaAiBot), if one is set up and
 * currently active — see App\Jobs\SendAiBotReply. Keyword rules always
 * take priority over the AI bot: a company that configured both gets
 * predictable, free, instant answers for known keywords, and the AI
 * bot only ever has to handle the open-ended long tail.
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

        // WhatsApp Channels/Newsletters (chat_jid ending in @newsletter)
        // are one-way broadcasts — WhatsApp's own servers reject any
        // attempt to send a normal message back into one (confirmed via
        // production logs: "wa: failed to send message: server returned
        // error 401"). A device subscribed to a channel will still get
        // this webhook fired for every post that lands in it, so this is
        // skipped up front rather than letting a keyword/AI match run
        // all the way to a doomed send attempt every time.
        if (str_ends_with($validated['chat_jid'], '@newsletter')) {
            Log::info('wa-auto-reply: chat_jid is a channel/newsletter, skipped (cannot reply to broadcasts)', [
                'device_id' => $validated['device_id'],
                'chat_jid' => $validated['chat_jid'],
            ]);

            return response()->json(['status' => 'skipped (newsletter/channel)']);
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
            return $this->tryAiBotFallback($validated);
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

    /**
     * Last resort when no keyword rule (and no default rule) matched:
     * hand the incoming message to the device's AI Bot, if one is
     * configured and currently switched on (see
     * App\Models\WaAiBot::isCurrentlyActive). If there's no bot, or it's
     * off, this is exactly the old "no match, do nothing" behaviour.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function tryAiBotFallback(array $validated): JsonResponse
    {
        $bot = WaAiBot::with(['provider', 'model'])
            ->where('device_id', $validated['device_id'])
            ->first();

        if (! $bot || ! $bot->isCurrentlyActive()) {
            Log::info('wa-auto-reply: no rule matched and no active AI bot configured', [
                'device_id' => $validated['device_id'],
                'body' => $validated['body'],
            ]);

            return response()->json(['status' => 'no match']);
        }

        Log::info('wa-auto-reply: no keyword matched, falling back to AI bot', [
            'ai_bot_id' => $bot->id,
            'device_id' => $validated['device_id'],
            'chat_jid' => $validated['chat_jid'],
        ]);

        SendAiBotReply::dispatch($bot->id, $validated['chat_jid'], $validated['body']);

        return response()->json([
            'status' => 'matched (ai bot)',
            'ai_bot_id' => $bot->id,
        ]);
    }
}
