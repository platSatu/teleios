<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Models\CompanyToUser;
use App\Models\WaContact;
use App\Models\WaConversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the whole lifecycle of App\Models\WaConversation: creating one the
 * first time a device+chat thread is seen, flipping its status as
 * messages flow in/out, computing/persisting SLA due dates, and picking
 * who gets auto-assigned to a brand new conversation. Every write to a
 * WaConversation row goes through here — controllers and webhook
 * handlers never touch the model directly, so the status machine and
 * SLA math can't drift out of sync between call sites.
 *
 * Deliberately has NO dependency on the current HTTP request/session
 * (unlike App\Http\Controllers\Concerns\ResolvesCompanyContext): both
 * its main entry points (recordInbound/recordOutbound) are called from
 * a server-to-server webhook with no logged-in user at all, so company
 * scoping is resolved from the device itself via App\Services\Chat\
 * DeviceDirectory.
 */
class ConversationService
{
    /** Used when a company hasn't set companies.chat_sla_first_response_minutes. */
    public const DEFAULT_FIRST_RESPONSE_MINUTES = 15;

    /** Used when a company hasn't set companies.chat_sla_resolution_minutes. */
    public const DEFAULT_RESOLUTION_MINUTES = 1440; // 24 hours

    /**
     * A brand new conversation's first inbound message and the webhook
     * retry Go may send for the exact same event could both race to
     * create the same (device_id, chat_jid) row — this lock makes the
     * second one wait instead of racing firstOrCreate(), which is not
     * safe against a concurrent duplicate on its own.
     */
    private const CREATE_LOCK_SECONDS = 10;

    public function __construct(protected DeviceDirectory $devices, protected CsatSurveyService $csatSurveys)
    {
    }

    /**
     * A new WhatsApp message just arrived. Creates the conversation row
     * the first time this (device, chat) is seen, or reopens/updates an
     * existing one. Safe to call for every single inbound message —
     * cheap no-ops once the row already reflects the latest state.
     */
    public function recordInbound(string $deviceId, string $chatJid, ?string $senderPhone, ?Carbon $sentAt = null): ?WaConversation
    {
        $sentAt ??= now();
        $lockKey = "wa-conversation:create-lock:{$deviceId}:{$chatJid}";

        return Cache::lock($lockKey, self::CREATE_LOCK_SECONDS)->block(5, function () use ($deviceId, $chatJid, $senderPhone, $sentAt) {
            return DB::transaction(function () use ($deviceId, $chatJid, $senderPhone, $sentAt) {
                $conversation = WaConversation::where('device_id', $deviceId)
                    ->where('chat_jid', $chatJid)
                    ->lockForUpdate()
                    ->first();

                if (! $conversation) {
                    $conversation = $this->createConversation($deviceId, $chatJid, $senderPhone, $sentAt);
                    $this->autoAssign($conversation);

                    return $conversation;
                }

                $conversation->last_inbound_at = $sentAt;

                if ($conversation->status === WaConversation::STATUS_RESOLVED) {
                    // Customer came back after the case was closed — a
                    // genuinely new cycle, so the SLA clock restarts
                    // from now rather than judging today's response
                    // against a due date computed when this reopened
                    // thread was still an old, already-closed case.
                    $this->reopenCycle($conversation, $sentAt);
                } elseif ($conversation->status === WaConversation::STATUS_PENDING) {
                    // Was waiting on the customer, they just replied —
                    // it's the agent's turn again. Same ongoing cycle,
                    // so opened_at/due dates are left untouched.
                    $conversation->status = WaConversation::STATUS_OPEN;
                }

                $conversation->save();

                if (! $conversation->assigned_to) {
                    $this->autoAssign($conversation);
                }

                return $conversation;
            });
        });
    }

    /**
     * An outbound message (an agent's own reply from the Inbox) was just
     * sent on this thread. Marks the response SLA as met (if this is the
     * first reply of the current cycle) and flips the conversation back
     * to "waiting on the customer".
     *
     * Deliberately only called from the agent-initiated send paths
     * (App\Http\Controllers\Chat\InboxController::send()/sendMedia()) —
     * NOT from automated sends (Auto Reply keyword rules, the AI Bot,
     * scheduled broadcasts). An instant automated reply "beating" the
     * SLA on every single conversation would make the metric meaningless
     * as a measure of actual team responsiveness; a human agent choosing
     * to reply (or not) is what this is meant to track.
     */
    public function recordOutbound(string $deviceId, string $chatJid, ?Carbon $sentAt = null): ?WaConversation
    {
        $sentAt ??= now();

        return DB::transaction(function () use ($deviceId, $chatJid, $sentAt) {
            $conversation = WaConversation::where('device_id', $deviceId)
                ->where('chat_jid', $chatJid)
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                // An agent messaging a chat before it ever received an
                // inbound message (outbound-initiated outreach) — start
                // a conversation row for it too, so it still shows up in
                // the queue and gets tracked going forward.
                $conversation = $this->createConversation($deviceId, $chatJid, null, $sentAt);
            }

            $conversation->last_outbound_at = $sentAt;

            if ($conversation->first_response_at === null) {
                $conversation->first_response_at = $sentAt;
            }

            if ($conversation->status === WaConversation::STATUS_OPEN) {
                $conversation->status = WaConversation::STATUS_PENDING;
            }

            $conversation->save();

            return $conversation;
        });
    }

    /**
     * Manual status change from the Inbox detail panel ("Tandai Selesai",
     * "Buka Kembali", etc), or an automated one from a Fitur #6 chatbot
     * flow's ACTION_SET_STATUS_RESOLVED/ACTION_SET_STATUS_PENDING step.
     * Reopening a resolved conversation restarts its SLA cycle the same
     * way a fresh inbound message would.
     *
     * The single choke point every status change funnels through is also
     * what makes this the right place to trigger Fitur #7's CSAT survey
     * (App\Services\Chat\CsatSurveyService::maybeSendSurvey()) — fired
     * only on a genuine OPEN/PENDING -> RESOLVED transition, never on a
     * redundant "already resolved, saved again" call, so a customer never
     * gets surveyed twice for the same closure.
     */
    public function setStatus(WaConversation $conversation, string $status): WaConversation
    {
        if (! in_array($status, WaConversation::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid conversation status: {$status}");
        }

        $resolved = DB::transaction(function () use ($conversation, $status) {
            $locked = WaConversation::whereKey($conversation->id)->lockForUpdate()->first();

            if (! $locked) {
                return [$conversation, false];
            }

            $justResolved = $status === WaConversation::STATUS_RESOLVED && $locked->status !== WaConversation::STATUS_RESOLVED;

            if ($status === WaConversation::STATUS_RESOLVED) {
                $locked->status = WaConversation::STATUS_RESOLVED;
                $locked->resolved_at = now();
            } elseif ($locked->status === WaConversation::STATUS_RESOLVED && $status !== WaConversation::STATUS_RESOLVED) {
                // Manual reopen — same "new cycle" treatment as a fresh
                // inbound message on a resolved thread.
                $this->reopenCycle($locked, now());
                $locked->status = $status;
            } else {
                $locked->status = $status;
            }

            $locked->save();

            return [$locked, $justResolved];
        });

        [$locked, $justResolved] = $resolved;

        if ($justResolved) {
            $this->csatSurveys->maybeSendSurvey($locked);
        }

        return $locked;
    }

    /**
     * Manual (re)assignment from the Inbox detail panel. $userId = null
     * clears the assignment back to "unassigned".
     */
    public function reassign(WaConversation $conversation, ?string $userId): WaConversation
    {
        $conversation->assigned_to = $userId;
        $conversation->assigned_at = $userId ? now() : null;
        $conversation->save();

        return $conversation;
    }

    /**
     * Bulk-flags every conversation whose SLA due date has passed
     * without being met yet. Two flat UPDATE statements regardless of
     * how many conversations exist — see App\Console\Commands\
     * EvaluateChatSlaBreaches, which calls this every minute.
     *
     * @return array{first_response: int, resolution: int} rows newly flagged
     */
    public function evaluateSlaBreaches(): array
    {
        $now = now();

        $firstResponse = WaConversation::query()
            ->whereIn('status', WaConversation::ACTIVE_STATUSES)
            ->whereNull('first_response_at')
            ->whereNotNull('sla_first_response_due_at')
            ->where('sla_first_response_due_at', '<', $now)
            ->where('first_response_breached', false)
            ->update(['first_response_breached' => true]);

        $resolution = WaConversation::query()
            ->whereIn('status', WaConversation::ACTIVE_STATUSES)
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', $now)
            ->where('resolution_breached', false)
            ->update(['resolution_breached' => true]);

        return ['first_response' => $firstResponse, 'resolution' => $resolution];
    }

    private function createConversation(string $deviceId, string $chatJid, ?string $senderPhone, Carbon $openedAt): WaConversation
    {
        $scope = $this->devices->scopeFor($deviceId);
        $due = $this->slaDueDates($scope['company_id'], $openedAt);

        return WaConversation::create([
            'company_id' => $scope['company_id'],
            'branch_office_id' => $scope['branch_office_id'],
            'device_id' => $deviceId,
            'chat_jid' => $chatJid,
            'contact_id' => $this->findContactId($scope['company_id'], $senderPhone),
            'status' => WaConversation::STATUS_OPEN,
            'opened_at' => $openedAt,
            'last_inbound_at' => $openedAt,
            'sla_first_response_due_at' => $due['first_response'],
            'sla_resolution_due_at' => $due['resolution'],
        ]);
    }

    private function reopenCycle(WaConversation $conversation, Carbon $reopenedAt): void
    {
        $due = $this->slaDueDates($conversation->company_id, $reopenedAt);

        $conversation->status = WaConversation::STATUS_OPEN;
        $conversation->opened_at = $reopenedAt;
        $conversation->resolved_at = null;
        $conversation->first_response_at = null;
        $conversation->sla_first_response_due_at = $due['first_response'];
        $conversation->sla_resolution_due_at = $due['resolution'];
        $conversation->first_response_breached = false;
        $conversation->resolution_breached = false;
    }

    /**
     * @return array{first_response: Carbon, resolution: Carbon}
     */
    private function slaDueDates(?string $companyId, Carbon $from): array
    {
        $firstResponseMinutes = self::DEFAULT_FIRST_RESPONSE_MINUTES;
        $resolutionMinutes = self::DEFAULT_RESOLUTION_MINUTES;

        if ($companyId) {
            $company = Company::query()->find($companyId, ['chat_sla_first_response_minutes', 'chat_sla_resolution_minutes']);

            if ($company) {
                $firstResponseMinutes = $company->chat_sla_first_response_minutes ?? $firstResponseMinutes;
                $resolutionMinutes = $company->chat_sla_resolution_minutes ?? $resolutionMinutes;
            }
        }

        return [
            'first_response' => $from->copy()->addMinutes($firstResponseMinutes),
            'resolution' => $from->copy()->addMinutes($resolutionMinutes),
        ];
    }

    private function findContactId(?string $companyId, ?string $senderPhone): ?string
    {
        if (! $companyId || ! $senderPhone) {
            return null;
        }

        $phone = WaContact::normalizePhone($senderPhone);

        if ($phone === '') {
            return null;
        }

        return WaContact::where('company_id', $companyId)->where('phone', $phone)->value('id');
    }

    /**
     * Picks who a brand new conversation gets handed to:
     *
     *   1. If it's already linked to a WaContact with an account owner
     *      (wa_contacts.assigned_to), that person gets it — an existing
     *      customer relationship always takes priority over load
     *      balancing.
     *   2. Otherwise, the least-busy eligible team member (fewest
     *      currently open/pending conversations) — a self-balancing
     *      queue that doesn't need a persisted round-robin cursor and
     *      naturally accounts for agents who are away/overloaded.
     *
     * A conversation with no company context (device not tied to any
     * Company) or a company with no eligible team members is left
     * unassigned — there's nobody to hand it to.
     */
    public function autoAssign(WaConversation $conversation): void
    {
        if (! $conversation->company_id) {
            return;
        }

        if ($conversation->contact_id) {
            $ownerId = WaContact::whereKey($conversation->contact_id)->value('assigned_to');

            if ($ownerId) {
                $this->reassign($conversation, $ownerId);

                return;
            }
        }

        $candidateIds = $this->eligibleTeamMemberIds($conversation->company_id, $conversation->branch_office_id);

        if ($candidateIds->isEmpty()) {
            return;
        }

        $loadCounts = WaConversation::query()
            ->where('company_id', $conversation->company_id)
            ->whereIn('status', WaConversation::ACTIVE_STATUSES)
            ->whereIn('assigned_to', $candidateIds)
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $leastBusy = $candidateIds
            ->sortBy(fn (string $userId) => (int) ($loadCounts[$userId] ?? 0))
            ->first();

        if ($leastBusy) {
            $this->reassign($conversation, $leastBusy);
        }
    }

    /**
     * Same eligibility rule as App\Http\Controllers\Concerns\
     * ResolvesCompanyContext::companyTeamMembers() (company owner, plus
     * every active CompanyToUser member, branch-scoped when the
     * conversation itself has a branch) — reimplemented here rather than
     * reused because that trait is designed around an inbound Request
     * and is meant for controllers, whereas this runs from a
     * server-to-server webhook with no request/session at all.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function eligibleTeamMemberIds(string $companyId, ?string $branchOfficeId): \Illuminate\Support\Collection
    {
        $memberQuery = CompanyToUser::where('company_id', $companyId)->where('status', 'active');

        if ($branchOfficeId) {
            $memberQuery->where(function ($q) use ($branchOfficeId) {
                $q->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
            });
        }

        $memberIds = $memberQuery->pluck('user_id');

        $ownerId = Company::whereKey($companyId)->value('user_id');

        return $memberIds->push($ownerId)->filter()->unique()->values();
    }
}
