<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaCustomer;
use App\Models\WaDeal;
use App\Services\Crm\CustomerAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Sales Pipeline" — CRM Roadmap Fase 3. A Kanban-style board of every
 * open opportunity (App\Models\WaDeal), grouped by its fixed stage (see
 * that model's docblock for why the stages themselves aren't
 * configurable).
 *
 * Server-rendered like Chat\PhoneBookController/Crm\CustomerTaskController
 * rather than a JS drag-and-drop board: moving a card between columns is
 * a plain auto-submitting `<select>` per card (see
 * resources/views/chat/deals/index.blade.php) posting to moveStage() —
 * no client-side board state to keep in sync, works with JS disabled
 * too.
 *
 * store()/update()/destroy() are also reachable from the "Pipeline
 * Penjualan" panel on the Customer 360 page
 * (resources/views/crm/customers/show.blade.php), same dual-entry-point
 * pattern App\Http\Controllers\Crm\CustomerTaskController already uses
 * for tasks.
 */
class DealController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = WaDeal::where('company_id', $company->id)
            ->with(['customer:id,name,phone', 'assignee:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $deals = $query->latest('created_at')->get();
        $dealsByStage = $deals->groupBy('stage');

        $columns = [];
        foreach (WaDeal::STAGES as $stage) {
            $stageDeals = $dealsByStage->get($stage, collect());
            $columns[] = [
                'stage' => $stage,
                'label' => WaDeal::STAGE_LABELS[$stage],
                'deals' => $stageDeals,
                'total' => $stageDeals->sum('value'),
            ];
        }

        $teamMembers = $this->companyTeamMembers(
            $company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return view('chat.deals.index', [
            'columns' => $columns,
            'stageLabels' => WaDeal::STAGE_LABELS,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'wa_customer_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $customer = WaCustomer::where('company_id', $context->company->id)
            ->where('id', $validated['wa_customer_id'])
            ->firstOrFail();

        if ($context->isLockedToBranch() && $customer->branch_office_id
            && $customer->branch_office_id !== $context->branchOffice?->id) {
            abort(403, 'Anda hanya bisa membuat deal untuk pelanggan di branch Anda sendiri.');
        }

        WaDeal::create([
            'company_id' => $context->company->id,
            'branch_office_id' => $customer->branch_office_id,
            'wa_customer_id' => $customer->id,
            'title' => $validated['title'],
            'value' => $validated['value'] ?? 0,
            'stage' => WaDeal::STAGE_LEAD,
            'expected_close_at' => $validated['expected_close_at'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Deal berhasil dibuat.');
    }

    public function edit(Request $request, string $deal): View
    {
        $context = $this->companyContext($request);

        $record = $this->findDeal($context->company->id, $deal);
        $record->load('customer:id,name,phone');

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return view('chat.deals.edit', [
            'deal' => $record,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function update(Request $request, string $deal): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findDeal($context->company->id, $deal);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $record->update($validated);

        return redirect()->route('chat.deals.index')->with('success', 'Deal berhasil diperbarui.');
    }

    /**
     * Moves a deal to a different stage — the board's one interactive
     * action. Setting/clearing `closed_at` here (rather than a model
     * event) keeps "what happens when a deal closes" in one obvious
     * place, same reasoning App\Services\Chat\ConversationService gives
     * for owning wa_conversations' status transitions exclusively.
     */
    public function moveStage(Request $request, string $deal, CustomerAutomationService $automation): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findDeal($context->company->id, $deal);

        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', WaDeal::STAGES)],
        ]);

        $newStage = $validated['stage'];
        $stageChanged = $newStage !== $record->stage;
        $wasClosed = $record->isClosed();
        $willBeClosed = in_array($newStage, WaDeal::CLOSED_STAGES, true);

        $record->stage = $newStage;
        $record->closed_at = $willBeClosed ? ($wasClosed ? $record->closed_at ?? now() : now()) : null;
        $record->save();

        // CRM Roadmap Fase 4 — only fire 'deal_stage_changed' automation
        // rules when the stage actually changed (the board re-submits
        // the same stage harmlessly whenever the card is just
        // re-rendered, and that should never re-fire an automation).
        if ($stageChanged) {
            $automation->fireDealStageChanged($record);
        }

        return back()->with('success', 'Tahap deal berhasil diubah.');
    }

    public function destroy(Request $request, string $deal): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findDeal($context->company->id, $deal);
        $record->delete();

        return back()->with('success', 'Deal berhasil dihapus.');
    }

    private function findDeal(string $companyId, string $dealId): WaDeal
    {
        return WaDeal::where('company_id', $companyId)
            ->where('id', $dealId)
            ->firstOrFail();
    }
}
