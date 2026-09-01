<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAiBotReply;
use App\Jobs\SendAutoReplyMessage;
use App\Jobs\SendChatbotFlowMessages;
use App\Jobs\SendOptOutConfirmationMessage;
use App\Models\User;
use App\Models\WaAiBot;
use App\Models\WaMessageAutoReply;
use App\Models\WaOptOut;
use App\Services\Chat\BroadcastOptOutService;
use App\Services\Chat\ChatbotFlowService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\DeviceDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
 *
 * Checked before the keyword-rule chain: Fitur #6's multi-step chatbot
 * flows (see tryChatbotFlow() below / App\Services\Chat\
 * ChatbotFlowService) — a customer already mid-flow has their reply
 * routed through the flow engine instead of the ordinary keyword chain,
 * and a message that happens to match a flow's own trigger_keyword
 * starts one. Only falls through to the plain keyword chain when the
 * message has nothing to do with any flow at all.
 *
 * Checked BEFORE all of the above: STOP/START-style opt-out/opt-in
 * replies (see tryOptOutKeyword()) — a compliance signal that must never
 * be shadowed by anything else this webhook does. See
 * App\Services\Chat\BroadcastOptOutService, enforced at actual send time
 * by App\Jobs\SendScheduledWaMessage.
 */
class WaIncomingMessageWebhookController extends Controller
{
    /**
     * Matched as a whole message (trimmed, case-insensitive), not a
     * substring match, so an unrelated longer message that merely
     * contains "stop" somewhere (e.g. "tolong stop dulu jadwalnya ya
     * kak") doesn't accidentally unsubscribe someone. Indonesian and
     * English both included since this app's audience is Indonesian
     * SMEs but "STOP" is the globally-recognized opt-out word WhatsApp
     * itself expects businesses to honor.
     */
    private const OPT_OUT_WORDS = ['stop', 'berhenti', 'unsub', 'unsubscribe', 'batal langganan'];

    private const OPT_IN_WORDS = ['start', 'mulai', 'subscribe', 'langganan lagi'];

    public function __construct(
        protected ConversationService $conversations,
        protected BroadcastOptOutService $optOuts,
        protected DeviceDirectory $devices,
        protected ChatbotFlowService $chatbotFlows
    ) {
    }

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
            // Real phone number of the sender, resolved on the Go side —
            // added because chat_jid alone is no longer reliably a phone
            // number: WhatsApp increasingly addresses chats via "@lid"
            // (Linked ID), an opaque internal identifier with no phone
            // digits in it at all (found live in production: a device's
            // chat_jid was a `...@lid` id with no phone number embedded,
            // which silently broke phone-based matching downstream). See
            // tryOptOutKeyword()'s fallback parsing below for how this is
            // used when sender_phone itself is empty (older Go builds not
            // yet redeployed with it).
            'sender_phone' => ['nullable', 'string'],
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

        // Opt-out/opt-in handling — checked before anything else below
        // (chatbot flows, keyword rules, the AI bot): a customer saying
        // STOP is a compliance/anti-ban signal, not a conversational
        // one, so it must never be shadowed by a company's own
        // auto-reply keyword that happens to also match. Short-circuits
        // the rest of this method when it fires.
        $optOutResponse = $this->tryOptOutKeyword($validated);

        if ($optOutResponse) {
            return $optOutResponse;
        }

        // Chat ops (status/SLA/auto-assignment) bookkeeping — see
        // App\Services\Chat\ConversationService. Best-effort and
        // isolated in its own try/catch: this is purely an internal
        // team-workflow concern, and a failure here (e.g. the device
        // isn't tied to any Company yet) must never block the
        // auto-reply/AI-bot logic below, which is what this webhook
        // exists for in the first place.
        try {
            $this->conversations->recordInbound(
                $validated['device_id'],
                $validated['chat_jid'],
                $validated['sender_phone'] ?? null,
                isset($validated['sent_at']) ? Carbon::parse($validated['sent_at']) : now()
            );
        } catch (Throwable $e) {
            Log::warning('wa-conversation: failed to record inbound message', [
                'device_id' => $validated['device_id'],
                'chat_jid' => $validated['chat_jid'],
                'error' => $e->getMessage(),
            ]);
        }

        $chatbotFlowResponse = $this->tryChatbotFlow($validated);

        if ($chatbotFlowResponse) {
            return $chatbotFlowResponse;
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
     * Routes an incoming message through Fitur #6's multi-step chatbot
     * flow engine (App\Services\Chat\ChatbotFlowService::handleIncoming)
     * — either continuing a session the customer is already inside, or
     * starting a new one if the message matches one of the device's flow
     * triggers. Returns null (falls through to the normal keyword/AI-bot
     * chain untouched) when the message has nothing to do with any flow
     * at all, exactly the same "null means not handled" contract every
     * other try*() method in this class already follows.
     *
     * The engine itself runs synchronously (it's cheap — a handful of
     * indexed row lookups) so this method can decide whether to
     * short-circuit; only the actual WhatsApp send is deferred to a
     * queued job (App\Jobs\SendChatbotFlowMessages), same split
     * SendAutoReplyMessage already uses for keyword rules.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function tryChatbotFlow(array $validated): ?JsonResponse
    {
        $result = $this->chatbotFlows->handleIncoming($validated['device_id'], $validated['chat_jid'], $validated['body'], $validated['sender_phone'] ?? null);

        if ($result === null) {
            return null;
        }

        if (! empty($result['messages'])) {
            $companyId = $this->devices->companyFor($validated['device_id']);

            if ($companyId) {
                SendChatbotFlowMessages::dispatch($companyId, $validated['device_id'], $validated['chat_jid'], $result['messages']);
            } else {
                Log::warning('chatbot-flow: device has no company context, cannot send flow reply', [
                    'device_id' => $validated['device_id'],
                ]);
            }
        }

        Log::info('chatbot-flow: message handled by flow engine', [
            'device_id' => $validated['device_id'],
            'chat_jid' => $validated['chat_jid'],
            'ended' => $result['ended'],
            'message_count' => count($result['messages']),
        ]);

        return response()->json(['status' => $result['ended'] ? 'flow ended' : 'flow in progress']);
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

    /**
     * Whole-message match against OPT_OUT_WORDS/OPT_IN_WORDS (trimmed,
     * case-insensitive, not a substring match) — on a match, records
     * the opt-out/opt-in via App\Services\Chat\BroadcastOptOutService
     * and queues a short acknowledgement reply, then short-circuits the
     * rest of handle() so this never also triggers a company's own
     * keyword auto-reply.
     *
     * Returns null (falls through to the normal chain untouched) when
     * the device isn't tied to any Company yet, the sender's phone
     * number couldn't be resolved, or the message simply isn't a
     * STOP/START-style reply at all.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function tryOptOutKeyword(array $validated): ?JsonResponse
    {
        $normalized = mb_strtolower(trim($validated['body']));
        $normalized = trim($normalized, ".!? \t\n\r");

        $isOptOut = in_array($normalized, self::OPT_OUT_WORDS, true);
        $isOptIn = ! $isOptOut && in_array($normalized, self::OPT_IN_WORDS, true);

        if (! $isOptOut && ! $isOptIn) {
            return null;
        }

        $companyId = $this->devices->companyFor($validated['device_id']);

        if (! $companyId) {
            Log::info('wa-opt-out: device has no company context, opt-out/in ignored', ['device_id' => $validated['device_id']]);

            return null;
        }

        $phone = $validated['sender_phone'] ?? null;
        $digits = $phone !== null && $phone !== '' ? preg_replace('/\D+/', '', $phone) : preg_replace('/\D+/', '', explode('@', $validated['chat_jid'])[0] ?? '');

        if ($digits === '') {
            return null;
        }

        if ($isOptOut) {
            $this->optOuts->optOut($companyId, $digits, WaOptOut::SOURCE_WA_REPLY);

            Log::info('wa-opt-out: number opted out via WA reply', ['company_id' => $companyId, 'phone' => $digits]);

            SendOptOutConfirmationMessage::dispatch(
                $companyId,
                $validated['device_id'],
                $validated['chat_jid'],
                'Anda telah berhenti berlangganan pesan promosi/broadcast dari kami. Balas MULAI kapan saja untuk berlangganan kembali.'
            );

            return response()->json(['status' => 'opted out', 'phone' => $digits]);
        }

        $reactivated = $this->optOuts->optIn($companyId, $digits);

        Log::info('wa-opt-out: number opted back in via WA reply', ['company_id' => $companyId, 'phone' => $digits, 'was_opted_out' => $reactivated]);

        SendOptOutConfirmationMessage::dispatch(
            $companyId,
            $validated['device_id'],
            $validated['chat_jid'],
            'Anda telah berlangganan kembali dan akan menerima pesan dari kami. Balas STOP kapan saja untuk berhenti.'
        );

        return response()->json(['status' => 'opted in', 'phone' => $digits, 'was_opted_out' => $reactivated]);
    }
}
