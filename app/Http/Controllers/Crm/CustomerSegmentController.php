<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\WaCustomerSegment;
use App\Models\WaCustomerTag;
use App\Models\WaDeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Segmentasi" — CRM Roadmap Fase 4's "segmen dinamis". Manages saved
 * App\Models\WaCustomerSegment filters and, on the same page, the
 * App\Models\WaCustomerTag catalog those filters (and the Customer 360
 * tagging panel) draw from — see App\Http\Controllers\Crm\
 * CustomerTagController for the tag catalog's own CRUD, kept in its own
 * controller since tags are a genuinely separate resource that also
 * gets attached/detached from outside this page.
 *
 * index()/show() both call App\Models\WaCustomerSegment::
 * matchingCustomersQuery() rather than reading any stored membership —
 * see that model's docblock for why a segment is a live filter, not a
 * list.
 */
class CustomerSegmentController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $segments = WaCustomerSegment::where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(function (WaCustomerSegment $segment) use ($company) {
                $segment->member_count = $segment->matchingCustomersQuery($company->id)->count();

                return $segment;
            });

        $tags = WaCustomerTag::where('company_id', $company->id)
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        $branches = BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('chat.segments.index', [
            'segments' => $segments,
            'tags' => $tags,
            'branches' => $branches,
            'dealStages' => WaDeal::STAGE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'tag_id' => ['nullable', 'uuid'],
            'deal_stage' => ['nullable', 'string', 'in:'.implode(',', WaDeal::STAGES)],
            'branch_office_id' => ['nullable', 'uuid'],
            'no_contact_days' => ['nullable', 'integer', 'min:1'],
            'has_overdue_task' => ['nullable', 'boolean'],
        ]);

        WaCustomerSegment::create([
            'company_id' => $context->company->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'filters' => $this->buildFilters($validated),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Segmen berhasil dibuat.');
    }

    public function edit(Request $request, string $segment): View
    {
        $context = $this->companyContext($request);

        $record = $this->findSegment($context->company->id, $segment);

        $tags = WaCustomerTag::where('company_id', $context->company->id)->orderBy('name')->get();
        $branches = BranchOffice::where('company_id', $context->company->id)->orderBy('name')->get(['id', 'name']);

        return view('chat.segments.edit', [
            'segment' => $record,
            'tags' => $tags,
            'branches' => $branches,
            'dealStages' => WaDeal::STAGE_LABELS,
        ]);
    }

    public function update(Request $request, string $segment): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findSegment($context->company->id, $segment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'tag_id' => ['nullable', 'uuid'],
            'deal_stage' => ['nullable', 'string', 'in:'.implode(',', WaDeal::STAGES)],
            'branch_office_id' => ['nullable', 'uuid'],
            'no_contact_days' => ['nullable', 'integer', 'min:1'],
            'has_overdue_task' => ['nullable', 'boolean'],
        ]);

        $record->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'filters' => $this->buildFilters($validated),
        ]);

        return redirect()->route('chat.segments.index')->with('success', 'Segmen berhasil diperbarui.');
    }

    public function destroy(Request $request, string $segment): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findSegment($context->company->id, $segment);
        $record->delete();

        return back()->with('success', 'Segmen berhasil dihapus.');
    }

    public function show(Request $request, string $segment): View
    {
        $context = $this->companyContext($request);

        $record = $this->findSegment($context->company->id, $segment);

        $customers = $record->matchingCustomersQuery($context->company->id)
            ->orderByDesc('last_contacted_at')
            ->paginate(20)
            ->withQueryString();

        return view('chat.segments.show', [
            'segment' => $record,
            'customers' => $customers,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildFilters(array $validated): array
    {
        return array_filter([
            'tag_id' => $validated['tag_id'] ?? null,
            'deal_stage' => $validated['deal_stage'] ?? null,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'no_contact_days' => $validated['no_contact_days'] ?? null,
            'has_overdue_task' => ! empty($validated['has_overdue_task']) ? true : null,
        ], fn ($value) => $value !== null);
    }

    private function findSegment(string $companyId, string $segmentId): WaCustomerSegment
    {
        return WaCustomerSegment::where('company_id', $companyId)
            ->where('id', $segmentId)
            ->firstOrFail();
    }
}
