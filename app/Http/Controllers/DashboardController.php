<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Models\JadwalReminderSetting;
use App\Services\Chat\ChatReportingService;
use App\Services\Company\CompanyContextResolver;
use App\Services\PackageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Main Dashboard shell. summary() used to live on Chat > Laporan (see
 * App\Http\Controllers\Chat\ChatReportController, now removed) — moved
 * here so the response/resolution, agent performance, broadcast and
 * CSAT numbers show up directly on the main Dashboard for every user
 * instead of being tucked behind the Chat menu's 'active.package' +
 * 'menu.access' gates. The underlying aggregate queries themselves are
 * untouched, still in App\Services\Chat\ChatReportingService.
 */
class DashboardController extends Controller
{
    use ResolvesCompanyContext;

    /** Matches the "last 30 days" window most reporting dashboards default to. */
    private const DEFAULT_WINDOW_DAYS = 30;

    private const EMPTY_SUMMARY = [
        'response_resolution' => [
            'total_conversations' => 0,
            'resolved_count' => 0,
            'avg_first_response_minutes' => null,
            'avg_resolution_minutes' => null,
            'first_response_breach_rate' => 0,
            'resolution_breach_rate' => 0,
        ],
        'agents' => [],
        'broadcast' => ['total' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'delivery_rate' => 0, 'read_rate' => 0],
        'csat' => ['avg_score' => null, 'sent_count' => 0, 'response_rate' => 0, 'score_distribution' => []],
    ];

    public function __construct(protected ChatReportingService $reports)
    {
    }

    public function index()
    {
        return view('dashboard.index');
    }

    /**
     * AJAX: the whole Chat reporting widget in one payload — response/
     * resolution summary, per-agent table, broadcast stats, and CSAT all
     * share the same ?from=/?to= window, so a single request is enough
     * for the dashboard card to render.
     *
     * Branch-locked members (see App\Services\Company\CompanyContext::
     * isLockedToBranch()) only ever see their own branch's numbers —
     * broadcast stats have no branch dimension on wa_message_schedules,
     * so that section is company-wide regardless.
     */
    public function summary(Request $request, PackageLimitService $packageLimits): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->resolveWindow($request);
        $period = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

        $user = $request->user();
        $context = $user->user_type === 'SUPERADMIN'
            ? null
            : app(CompanyContextResolver::class)->resolve($user, session('active_company_id'));
        $branch = $context?->activeBranch();

        // Belum punya company/branch/paket Chat aktif (aturan sama dengan
        // menu Chat di sidebar): kartu dashboard tetap tampil, isinya nol.
        if (! $branch || ! $packageLimits->hasActiveCategoryPackage($context->company, JadwalReminderSetting::CHAT_CATEGORY_NAMES, $branch)) {
            return response()->json(['period' => $period] + self::EMPTY_SUMMARY);
        }

        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return response()->json([
            'period' => $period,
            'response_resolution' => $this->reports->responseAndResolutionSummary($context->company->id, $from, $to, $branchOfficeId),
            'agents' => $this->reports->agentPerformance($context->company->id, $from, $to, $branchOfficeId)->values(),
            'broadcast' => $this->reports->broadcastDeliveryStats($context->company->id, $from, $to),
            'csat' => $this->reports->csatSummary($context->company->id, $from, $to, $branchOfficeId),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWindow(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->value())->endOfDay()
            : now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->value())->startOfDay()
            : $to->copy()->subDays(self::DEFAULT_WINDOW_DAYS - 1)->startOfDay();

        // A swapped/inverted range (from > to) would otherwise silently
        // return zeroed-out results from every query above rather than
        // an obvious error — swapping it back is friendlier than making
        // the caller re-request with the fields in the "right" order.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
