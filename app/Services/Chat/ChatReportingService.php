<?php

namespace App\Services\Chat;

use App\Models\WaConversation;
use App\Models\WaCsatSurvey;
use App\Models\WaMessageScheduleLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregate metrics for Chat's reporting dashboard: response/resolution
 * time, per-agent performance (both from App\Models\WaConversation, the
 * chat-ops layer built for Fitur #1), and broadcast delivery/read rates
 * (from App\Models\WaMessageScheduleLog, the anti-ban broadcast layer
 * from Fitur #2). Ties both earlier features together into the numbers a
 * team actually wants to see.
 *
 * Every query here is a single flat SQL aggregate (AVG/COUNT/SUM, or one
 * GROUP BY) scoped to (company_id, date range) — never "load every row
 * into PHP and compute in userland". That's what keeps this cheap enough
 * to run on every dashboard page load regardless of how many
 * conversations/broadcasts a company has accumulated; see the migration
 * that added wa_conversations' (company_id, opened_at) index for the
 * other half of that (a query is only as fast as the index backing it).
 */
class ChatReportingService
{
    /**
     * Response/resolution time averages plus SLA breach counts, for
     * conversations OPENED within [$from, $to] — deliberately windowed
     * by opened_at (not resolved_at), so a report for "last 7 days"
     * means "conversations that started in the last 7 days", not
     * "conversations that happened to close in the last 7 days" (which
     * would misleadingly exclude everything still open).
     *
     * @return array{
     *     total_conversations: int,
     *     resolved_count: int,
     *     avg_first_response_minutes: ?float,
     *     avg_resolution_minutes: ?float,
     *     first_response_breached_count: int,
     *     resolution_breached_count: int,
     *     first_response_breach_rate: float,
     *     resolution_breach_rate: float,
     * }
     */
    public function responseAndResolutionSummary(string $companyId, Carbon $from, Carbon $to, ?string $branchOfficeId = null): array
    {
        $row = $this->scopedConversations($companyId, $from, $to, $branchOfficeId)
            ->selectRaw('COUNT(*) as total_conversations')
            ->selectRaw('COUNT(resolved_at) as resolved_count')
            ->selectRaw('AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, opened_at, first_response_at) END) as avg_first_response_minutes')
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, opened_at, resolved_at) END) as avg_resolution_minutes')
            ->selectRaw('SUM(first_response_breached) as first_response_breached_count')
            ->selectRaw('SUM(resolution_breached) as resolution_breached_count')
            ->first();

        $total = (int) ($row->total_conversations ?? 0);
        $firstResponseBreached = (int) ($row->first_response_breached_count ?? 0);
        $resolutionBreached = (int) ($row->resolution_breached_count ?? 0);

        return [
            'total_conversations' => $total,
            'resolved_count' => (int) ($row->resolved_count ?? 0),
            'avg_first_response_minutes' => $this->roundOrNull($row->avg_first_response_minutes ?? null),
            'avg_resolution_minutes' => $this->roundOrNull($row->avg_resolution_minutes ?? null),
            'first_response_breached_count' => $firstResponseBreached,
            'resolution_breached_count' => $resolutionBreached,
            'first_response_breach_rate' => $this->rate($firstResponseBreached, $total),
            'resolution_breach_rate' => $this->rate($resolutionBreached, $total),
        ];
    }

    /**
     * Per-agent breakdown for the same window — one row per team member
     * who had at least one conversation assigned to them, plus a
     * synthetic "unassigned" row (assigned_to null) so a company can see
     * at a glance how many conversations nobody ever picked up, which is
     * itself an important operational signal
     * App\Services\Chat\ConversationService::autoAssign() alone can't
     * guarantee away (e.g. a company with zero eligible team members).
     *
     * @return Collection<int, array{
     *     user_id: ?string,
     *     name: string,
     *     conversations_handled: int,
     *     resolved_count: int,
     *     avg_first_response_minutes: ?float,
     *     avg_resolution_minutes: ?float,
     *     breached_count: int,
     * }>
     */
    public function agentPerformance(string $companyId, Carbon $from, Carbon $to, ?string $branchOfficeId = null): Collection
    {
        $rows = $this->scopedConversations($companyId, $from, $to, $branchOfficeId)
            ->leftJoin('users', 'users.id', '=', 'wa_conversations.assigned_to')
            ->selectRaw('wa_conversations.assigned_to as user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('COUNT(*) as conversations_handled')
            ->selectRaw('COUNT(wa_conversations.resolved_at) as resolved_count')
            ->selectRaw('AVG(CASE WHEN wa_conversations.first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, wa_conversations.opened_at, wa_conversations.first_response_at) END) as avg_first_response_minutes')
            ->selectRaw('AVG(CASE WHEN wa_conversations.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, wa_conversations.opened_at, wa_conversations.resolved_at) END) as avg_resolution_minutes')
            ->selectRaw('SUM(CASE WHEN wa_conversations.first_response_breached = 1 OR wa_conversations.resolution_breached = 1 THEN 1 ELSE 0 END) as breached_count')
            ->groupBy('wa_conversations.assigned_to', 'users.name')
            ->orderByDesc('conversations_handled')
            ->get();

        return $rows->map(fn ($row) => [
            'user_id' => $row->user_id,
            'name' => $row->user_id ? ($row->user_name ?? 'Pengguna tidak dikenal') : 'Belum Ditugaskan',
            'conversations_handled' => (int) $row->conversations_handled,
            'resolved_count' => (int) $row->resolved_count,
            'avg_first_response_minutes' => $this->roundOrNull($row->avg_first_response_minutes),
            'avg_resolution_minutes' => $this->roundOrNull($row->avg_resolution_minutes),
            'breached_count' => (int) $row->breached_count,
        ]);
    }

    /**
     * Broadcast send outcomes for the window, windowed by
     * wa_message_schedule_logs.send_date (the calendar day a recipient
     * was due, not when the underlying schedule was created) — joins to
     * wa_message_schedules only to scope by company_id, since the log
     * table itself doesn't carry that column (see that migration's
     * docblock for why recipients live where they do).
     *
     * @return array{
     *     total: int, sent: int, delivered: int, read: int, failed: int, skipped: int, pending: int,
     *     delivery_rate: float, read_rate: float,
     * }
     */
    public function broadcastDeliveryStats(string $companyId, Carbon $from, Carbon $to): array
    {
        $counts = WaMessageScheduleLog::query()
            ->join('wa_message_schedules', 'wa_message_schedules.id', '=', 'wa_message_schedule_logs.wa_message_schedule_id')
            ->where('wa_message_schedules.company_id', $companyId)
            ->whereBetween('wa_message_schedule_logs.send_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('wa_message_schedule_logs.status as status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('wa_message_schedule_logs.status')
            ->pluck('total', 'status');

        // 'sent'/'delivered'/'read' form a ladder (see WaMessageScheduleLog::
        // STATUS_RANK) — a row currently sitting at 'read' already passed
        // through 'sent' and 'delivered' too, so those two counts are
        // reported as "at least this far", not "stuck exactly here".
        $sentOrBetter = (int) ($counts->get('sent', 0) + $counts->get('delivered', 0) + $counts->get('read', 0));
        $deliveredOrBetter = (int) ($counts->get('delivered', 0) + $counts->get('read', 0));
        $read = (int) $counts->get('read', 0);
        $failed = (int) $counts->get('failed', 0);
        $skipped = (int) $counts->get('skipped', 0);
        $pending = (int) $counts->get('pending', 0);
        $total = $sentOrBetter + $failed + $skipped + $pending;

        return [
            'total' => $total,
            'sent' => $sentOrBetter,
            'delivered' => $deliveredOrBetter,
            'read' => $read,
            'failed' => $failed,
            'skipped' => $skipped,
            'pending' => $pending,
            'delivery_rate' => $this->rate($deliveredOrBetter, $sentOrBetter),
            'read_rate' => $this->rate($read, $deliveredOrBetter),
        ];
    }

    /**
     * CSAT (Customer Satisfaction) summary for the window — Fitur #7,
     * windowed by wa_csat_surveys.sent_at (when the survey went out, same
     * "windowed by when the thing started" convention
     * responseAndResolutionSummary() above uses for opened_at) rather
     * than responded_at, so a report for "last 7 days" means "surveys
     * sent in the last 7 days", not silently excluding ones sent in that
     * window a customer simply hasn't answered yet.
     *
     * @return array{
     *     sent_count: int,
     *     response_count: int,
     *     response_rate: float,
     *     avg_score: ?float,
     *     score_distribution: array<int, int>,
     * }
     */
    public function csatSummary(string $companyId, Carbon $from, Carbon $to, ?string $branchOfficeId = null): array
    {
        $row = WaCsatSurvey::query()
            ->where('company_id', $companyId)
            ->whereBetween('sent_at', [$from, $to])
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->selectRaw('COUNT(*) as sent_count')
            ->selectRaw('COUNT(responded_at) as response_count')
            ->selectRaw('AVG(score) as avg_score')
            ->first();

        $sentCount = (int) ($row->sent_count ?? 0);
        $responseCount = (int) ($row->response_count ?? 0);

        $distributionRows = WaCsatSurvey::query()
            ->where('company_id', $companyId)
            ->whereBetween('sent_at', [$from, $to])
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereNotNull('score')
            ->selectRaw('score, COUNT(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        // Every score 1-5 always present in the output (zero-filled),
        // even ones nobody picked — a report/chart consumer shouldn't
        // have to guess whether a missing key means "zero responses" or
        // "this endpoint doesn't track that score".
        $distribution = [];
        for ($score = 1; $score <= 5; $score++) {
            $distribution[$score] = (int) ($distributionRows[$score] ?? 0);
        }

        return [
            'sent_count' => $sentCount,
            'response_count' => $responseCount,
            'response_rate' => $this->rate($responseCount, $sentCount),
            'avg_score' => $this->roundOrNull($row->avg_score ?? null),
            'score_distribution' => $distribution,
        ];
    }

    private function scopedConversations(string $companyId, Carbon $from, Carbon $to, ?string $branchOfficeId): Builder
    {
        return WaConversation::query()
            ->where('wa_conversations.company_id', $companyId)
            ->whereBetween('wa_conversations.opened_at', [$from, $to])
            ->when($branchOfficeId, fn ($q) => $q->where('wa_conversations.branch_office_id', $branchOfficeId));
    }

    private function roundOrNull(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
