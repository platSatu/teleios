<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Models\WaChatbotFlow;
use App\Models\WaChatbotFlowStep;
use App\Models\WaChatbotState;
use App\Models\WaChatLabelAssignment;
use App\Models\WaConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes App\Models\WaChatbotFlow trees — Fitur #6's engine. Called
 * from App\Http\Controllers\Api\WaIncomingMessageWebhookController for
 * every incoming message, BEFORE the ordinary keyword auto-reply chain
 * (see that controller's docblock for the full precedence order): a
 * customer either continues a flow session they're already inside
 * (App\Models\WaChatbotState), or their message triggers a brand new one.
 * Either way, this is deliberately synchronous (unlike the actual WhatsApp
 * send, which is queued via App\Jobs\SendChatbotFlowMessages) — the
 * webhook needs to know RIGHT NOW whether a customer's reply belongs to a
 * flow, so it can either short-circuit or fall through to the keyword
 * chain accordingly.
 *
 * The four step types (see WaChatbotFlowStep::TYPE_*) are walked in
 * walk() below: 'action' steps execute immediately and chain straight
 * into whatever comes next with no waiting, while 'message'/'choice'
 * steps stop and wait for the customer's next reply. This is what lets
 * one flow definition mix pure automation ("tag this chat, assign it to
 * Budi") with actual back-and-forth conversation in any order a company
 * wants.
 */
class ChatbotFlowService
{
    /**
     * Safety cap on how many steps a single call to walk() will chain
     * through automatically (i.e. consecutive 'action' steps with no
     * waiting in between). A company misconfiguring a flow with a
     * circular action-only chain must never turn one incoming webhook
     * request into an infinite loop — this is far more hops than any
     * legitimate flow (a handful of automated steps in a row) needs.
     */
    private const MAX_AUTO_ADVANCE_STEPS = 25;

    public function __construct(
        protected ConversationService $conversations,
        protected AutoReplyTagResolver $tagResolver,
    ) {
    }

    /**
     * Entry point for the webhook. Returns null when this message has
     * nothing to do with any flow (no active session, no trigger
     * matched) — the caller should fall through to the ordinary
     * keyword/AI-bot chain in that case, exactly as if this service
     * didn't exist at all.
     *
     * @return array{messages: array<int, string>, ended: bool}|null
     */
    public function handleIncoming(string $deviceId, string $chatJid, string $body): ?array
    {
        $state = $this->activeState($deviceId, $chatJid);

        if ($state) {
            return $this->continueFlow($state, $body);
        }

        $flow = $this->findTriggeredFlow($deviceId, $body);

        if (! $flow) {
            return null;
        }

        return $this->start($flow, $deviceId, $chatJid);
    }

    /**
     * The customer's currently in-progress session for this chat, if any
     * — self-expiring: a session whose flow was deleted/deactivated, or
     * that's been idle longer than its flow's session_timeout_minutes, is
     * cleaned up here rather than left to accumulate forever, and treated
     * as "no active session" either way.
     */
    public function activeState(string $deviceId, string $chatJid): ?WaChatbotState
    {
        $state = WaChatbotState::where('device_id', $deviceId)->where('chat_jid', $chatJid)->first();

        if (! $state) {
            return null;
        }

        $flow = $state->flow;

        if (! $flow || $flow->status !== WaChatbotFlow::STATUS_ACTIVE) {
            $state->delete();

            return null;
        }

        $timeoutMinutes = $flow->session_timeout_minutes ?: WaChatbotFlow::DEFAULT_SESSION_TIMEOUT_MINUTES;

        if ($state->last_interaction_at && $state->last_interaction_at->lt(now()->subMinutes($timeoutMinutes))) {
            Log::info('chatbot-flow: session timed out, cleared', [
                'flow_id' => $flow->id,
                'device_id' => $deviceId,
                'chat_jid' => $chatJid,
            ]);

            $state->delete();

            return null;
        }

        return $state;
    }

    /**
     * The first active flow (oldest-created first, same convention as
     * WaMessageAutoReply's rule ordering) whose trigger_keyword matches
     * this message, if any.
     */
    public function findTriggeredFlow(string $deviceId, string $body): ?WaChatbotFlow
    {
        return WaChatbotFlow::where('device_id', $deviceId)
            ->where('status', WaChatbotFlow::STATUS_ACTIVE)
            ->oldest()
            ->get()
            ->first(fn (WaChatbotFlow $flow) => $flow->matchesTrigger($body));
    }

    /**
     * @return array{messages: array<int, string>, ended: bool}
     */
    public function start(WaChatbotFlow $flow, string $deviceId, string $chatJid): array
    {
        $startStep = $flow->steps()->where('is_start', true)->first();

        if (! $startStep) {
            Log::warning('chatbot-flow: flow has no start step configured, cannot start', ['flow_id' => $flow->id]);

            return ['messages' => [], 'ended' => true];
        }

        // Same create-race guard as App\Services\Chat\ConversationService
        // ::recordInbound() — two near-simultaneous webhook deliveries
        // for this same chat (e.g. a customer double-sending the trigger
        // keyword) could otherwise both pass findTriggeredFlow() before
        // either has written its wa_chatbot_states row, racing
        // updateOrCreate() against the table's own unique(device_id,
        // chat_jid) constraint.
        $lockKey = "chatbot-flow:start-lock:{$deviceId}:{$chatJid}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($flow, $deviceId, $chatJid, $startStep) {
            return DB::transaction(function () use ($flow, $deviceId, $chatJid, $startStep) {
                $state = WaChatbotState::updateOrCreate(
                    ['device_id' => $deviceId, 'chat_jid' => $chatJid],
                    [
                        'wa_chatbot_flow_id' => $flow->id,
                        'current_step_id' => $startStep->id,
                        'variables' => [],
                        'started_at' => now(),
                        'last_interaction_at' => now(),
                    ]
                );
                $state->setRelation('flow', $flow);

                Log::info('chatbot-flow: session started', ['flow_id' => $flow->id, 'device_id' => $deviceId, 'chat_jid' => $chatJid]);

                return $this->walk($state, $startStep);
            });
        });
    }

    /**
     * Evaluates the customer's reply against whatever step they were
     * waiting at, records it, and advances — see walk() for what happens
     * next. A 'choice' step whose reply matches none of its options
     * REPROMPTS the same step (with a short "not recognized" prefix)
     * rather than advancing on a guess or silently dropping the message.
     *
     * @return array{messages: array<int, string>, ended: bool}
     */
    public function continueFlow(WaChatbotState $state, string $body): array
    {
        return DB::transaction(function () use ($state, $body) {
            $locked = WaChatbotState::whereKey($state->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['messages' => [], 'ended' => true];
            }

            $currentStep = $locked->currentStep;

            if (! $currentStep) {
                $locked->delete();

                return ['messages' => [], 'ended' => true];
            }

            $variables = $locked->variables ?? [];
            $nextStepId = $currentStep->default_next_step_id;

            if ($currentStep->step_type === WaChatbotFlowStep::TYPE_CHOICE) {
                $matched = $this->matchChoiceOption($currentStep, $body);

                if ($matched === null) {
                    $locked->last_interaction_at = now();
                    $locked->save();

                    $reprompt = trim(
                        'Maaf, pilihan tidak dikenali. '
                        .$this->renderStepMessage($currentStep, $currentStep->flow?->company)
                        ."\n".$this->renderChoiceOptions($currentStep)
                    );

                    return ['messages' => [$reprompt], 'ended' => false];
                }

                $variables[$currentStep->id] = $matched['label'] ?? ($matched['value'] ?? null);
                $nextStepId = $matched['next_step_id'] ?? $currentStep->default_next_step_id;
            } else {
                // 'message' step — free-text reply, stored as-is.
                $variables[$currentStep->id] = trim($body);
            }

            $locked->variables = $variables;
            $locked->save();

            $nextStep = $nextStepId ? WaChatbotFlowStep::find($nextStepId) : null;

            return $this->walk($locked, $nextStep);
        });
    }

    /**
     * Walks forward from $step, executing every 'action' step it passes
     * through immediately (with no waiting) and stopping at the first
     * 'message'/'choice' step (which needs a reply) or 'end' step (which
     * terminates the session). Every step's own `message` (if any) is
     * collected along the way — a chain of several action steps in a row
     * can therefore produce several messages to send, in order.
     *
     * @return array{messages: array<int, string>, ended: bool}
     */
    private function walk(WaChatbotState $state, ?WaChatbotFlowStep $step): array
    {
        $flow = $step?->flow ?? $state->flow;
        $company = $flow?->company;
        $messages = [];
        $hops = 0;

        while ($step !== null) {
            if (++$hops > self::MAX_AUTO_ADVANCE_STEPS) {
                Log::warning('chatbot-flow: step chain exceeded safety limit, session ended', [
                    'flow_id' => $flow?->id,
                    'state_id' => $state->id,
                ]);
                $step = null;
                break;
            }

            // 'choice' steps: the step's own body text and its numbered
            // options list are combined into ONE WhatsApp message (joined
            // with "\n", then trimmed) instead of two separate messages —
            // matches the reprompt path in continueFlow() above, which
            // already combines them the same way. Handled before the
            // generic renderStepMessage() push below so the body text
            // isn't sent twice.
            if ($step->step_type === WaChatbotFlowStep::TYPE_CHOICE) {
                $combined = trim(
                    $this->renderStepMessage($step, $company)
                    ."\n".$this->renderChoiceOptions($step)
                );

                if ($combined !== '') {
                    $messages[] = $combined;
                }

                $state->current_step_id = $step->id;
                $state->last_interaction_at = now();
                $state->save();

                return ['messages' => $messages, 'ended' => false];
            }

            $rendered = $this->renderStepMessage($step, $company);
            if ($rendered !== '') {
                $messages[] = $rendered;
            }

            if ($step->step_type === WaChatbotFlowStep::TYPE_END) {
                $step = null;
                break;
            }

            if ($step->step_type === WaChatbotFlowStep::TYPE_ACTION) {
                $this->executeAction($state, $step);

                if ($step->action === WaChatbotFlowStep::ACTION_HANDOFF_HUMAN) {
                    $step = null; // handing off to a human always ends the bot session
                    break;
                }

                $step = $step->default_next_step_id ? WaChatbotFlowStep::find($step->default_next_step_id) : null;
                continue;
            }

            // 'message' — this step waits for a reply.
            $state->current_step_id = $step->id;
            $state->last_interaction_at = now();
            $state->save();

            return ['messages' => $messages, 'ended' => false];
        }

        // Ran off the end of the chain — nothing left to wait for.
        $state->delete();

        return ['messages' => $messages, 'ended' => true];
    }

    private function renderStepMessage(WaChatbotFlowStep $step, ?Company $company): string
    {
        if (! $step->message) {
            return '';
        }

        return $company ? $this->tagResolver->resolve($step->message, $company) : $step->message;
    }

    /**
     * Numbered list built from a 'choice' step's options, matching what
     * matchChoiceOption() below accepts as a reply ("1", "2", ...).
     */
    private function renderChoiceOptions(WaChatbotFlowStep $step): string
    {
        $options = collect($step->options ?? [])->values();

        if ($options->isEmpty()) {
            return '';
        }

        return $options->map(fn ($opt, int $i) => ($i + 1).'. '.($opt['label'] ?? ''))->implode("\n");
    }

    /**
     * Matches a reply against a 'choice' step's options either by number
     * (the primary expected way, matching renderChoiceOptions()'s
     * numbered list) or by the option's own label typed out verbatim
     * (case-insensitive) — some customers reply with the word instead of
     * the number. Returns null when neither matches.
     *
     * @return array{label?: string, value?: string, next_step_id?: ?string}|null
     */
    private function matchChoiceOption(WaChatbotFlowStep $step, string $body): ?array
    {
        $options = collect($step->options ?? [])->values();
        $normalized = mb_strtolower(trim($body));

        if (ctype_digit($normalized)) {
            $index = ((int) $normalized) - 1;
            if ($options->has($index)) {
                return $options->get($index);
            }
        }

        return $options->first(
            fn ($opt) => mb_strtolower(trim($opt['label'] ?? '')) === $normalized
        );
    }

    /**
     * Runs one 'action' step's automated effect against this chat's
     * App\Models\WaConversation row. Best-effort: if no conversation row
     * exists yet for this (device, chat) — e.g. App\Services\Chat\
     * ConversationService::recordInbound() failed earlier in the same
     * webhook request — the action is skipped rather than throwing, since
     * that must never break the flow itself from advancing.
     */
    private function executeAction(WaChatbotState $state, WaChatbotFlowStep $step): void
    {
        $conversation = WaConversation::where('device_id', $state->device_id)
            ->where('chat_jid', $state->chat_jid)
            ->first();

        if (! $conversation) {
            Log::info('chatbot-flow: action step skipped, no conversation row for this chat yet', [
                'step_id' => $step->id,
                'action' => $step->action,
            ]);

            return;
        }

        match ($step->action) {
            WaChatbotFlowStep::ACTION_ASSIGN_CONVERSATION => $step->action_value
                ? $this->conversations->reassign($conversation, $step->action_value)
                : $this->conversations->autoAssign($conversation),
            WaChatbotFlowStep::ACTION_SET_STATUS_PENDING => $this->conversations->setStatus($conversation, WaConversation::STATUS_PENDING),
            WaChatbotFlowStep::ACTION_SET_STATUS_RESOLVED => $this->conversations->setStatus($conversation, WaConversation::STATUS_RESOLVED),
            WaChatbotFlowStep::ACTION_ADD_LABEL => $this->addLabel($conversation, $step->action_value),
            WaChatbotFlowStep::ACTION_HANDOFF_HUMAN => $this->conversations->setStatus($conversation, WaConversation::STATUS_PENDING),
            default => null,
        };
    }

    private function addLabel(WaConversation $conversation, ?string $labelId): void
    {
        if (! $labelId) {
            return;
        }

        WaChatLabelAssignment::firstOrCreate([
            'wa_chat_label_id' => $labelId,
            'company_id' => $conversation->company_id,
            'device_id' => $conversation->device_id,
            'chat_jid' => $conversation->chat_jid,
        ]);
    }
}
