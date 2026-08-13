<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Services\Chat\ChatReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Chat's reporting dashboard: response/resolution time, per-agent
 * performance, and broadcast delivery/read rates — see
 * App\Services\Chat\ChatReportingService for the actual metric queries,
 * this controller only resolves the requesting company/branch/date-range
 * and shapes the JSON response.
 */
class ChatReportController extends Controller
{
    use ResolvesCompanyContext;

    /** Matches the "last 30 days" window most reporting dashboards default to. */
    private const DEFAULT_WINDOW_DAYS = 30;

    public function __construct(protected ChatReportingService $reports)
    {
    }

    /**
     * The reporting dashboard page shell — see summary() for the JSON
     * data it polls.
     */
    public function index(): View
    {
        return view('chat.reports.index');
    }

    /**
     * AJAX: the whole dashboard in one payload — response/resolution
     * summary, per-agent table, and broadcast stats all share the same
     * ?from=/?to= window, so a single request is enough for the page to
     * render instead of firing 3 separate ones for what's really one
     * "reporting period" concept.
     *
     * Branch-locked members (see App\Services\Company\CompanyContext::
     * isLockedToBranch()) only ever see their own branch's numbers —
     * broadcast stats have no branch dimension on wa_message_schedules,
     * so that section is company-wide regardless (same as every other
     * broadcast-related screen in this app).
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $context = $this->companyContext($request);
        [$from, $to] = $this->resolveWindow($request);
        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
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
