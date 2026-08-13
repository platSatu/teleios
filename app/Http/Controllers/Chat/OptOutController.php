<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaContact;
use App\Models\WaOptOut;
use App\Services\Chat\BroadcastOptOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a company see and manage its own broadcast opt-out list —
 * numbers are normally added here automatically (a customer's STOP
 * reply, see App\Http\Controllers\Api\WaIncomingMessageWebhookController
 * ::tryOptOutKeyword()), but a team also needs to add one manually
 * (a customer asks to be removed by phone/email instead of WA) and to
 * see the list at all for basic compliance visibility — this is
 * otherwise an invisible table nobody could ever audit.
 *
 * All writes go through App\Services\Chat\BroadcastOptOutService, same
 * as the webhook handler, so "is this number opted out" only ever has
 * one code path.
 */
class OptOutController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected BroadcastOptOutService $optOuts)
    {
    }

    /**
     * The opt-out list page shell — see list() for the JSON data it
     * polls.
     */
    public function page(): View
    {
        return view('chat.opt-outs.index');
    }

    /**
     * AJAX: paginated opt-out list for the logged-in user's company,
     * optionally filtered by ?search= against the phone number.
     */
    public function list(Request $request): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $optOuts = WaOptOut::where('company_id', $company->id)
            ->with('creator:id,name')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('phone', 'like', '%'.WaContact::normalizePhone((string) $request->string('search')).'%');
            })
            ->latest('opted_out_at')
            ->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'opt_outs' => collect($optOuts->items())->map(fn (WaOptOut $o) => [
                'id' => $o->id,
                'phone' => $o->phone,
                'source' => $o->source,
                'note' => $o->note,
                'created_by_name' => $o->creator?->name,
                'opted_out_at' => $o->opted_out_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $optOuts->currentPage(),
                'last_page' => $optOuts->lastPage(),
                'total' => $optOuts->total(),
            ],
        ]);
    }

    /**
     * AJAX: manually add a number to the opt-out list (e.g. a customer
     * asked to be removed via phone/email rather than a WA reply).
     */
    public function store(Request $request): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $optOut = $this->optOuts->optOut(
            $company->id,
            $validated['phone'],
            WaOptOut::SOURCE_MANUAL,
            $validated['note'] ?? null,
            $request->user()?->id
        );

        return response()->json(['status' => 'ok', 'id' => $optOut->id, 'phone' => $optOut->phone]);
    }

    /**
     * AJAX: remove a number from the opt-out list (re-subscribe it to
     * future broadcasts).
     */
    public function destroy(Request $request, string $optOut): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $row = WaOptOut::where('company_id', $company->id)->where('id', $optOut)->firstOrFail();

        $this->optOuts->optIn($company->id, $row->phone);

        return response()->json(['status' => 'ok']);
    }
}
