<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaCustomerAutomationRule;
use App\Models\WaCustomerTag;
use App\Models\WaDeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Automasi" — CRM Roadmap Fase 4's "automasi follow-up berbasis
 * trigger". CRUD for App\Models\WaCustomerAutomationRule; every rule
 * created/edited here is actually evaluated and executed by
 * App\Services\Crm\CustomerAutomationService, never by this controller
 * — this only ever reads/writes the rule definition itself.
 */
class CustomerAutomationRuleController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $rules = WaCustomerAutomationRule::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        return view('chat.automation-rules.index', [
            'rules' => $rules,
            'tags' => WaCustomerTag::where('company_id', $company->id)->orderBy('name')->get(),
            'dealStages' => WaDeal::STAGE_LABELS,
            'teamMembers' => $this->companyTeamMembers($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);

        $validated = $this->validateRule($request);

        WaCustomerAutomationRule::create([
            'company_id' => $context->company->id,
            'name' => $validated['name'],
            'trigger_type' => $validated['trigger_type'],
            'trigger_config' => $this->buildTriggerConfig($validated),
            'action_type' => WaCustomerAutomationRule::ACTION_CREATE_TASK,
            'action_config' => $this->buildActionConfig($validated),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Aturan automasi berhasil dibuat.');
    }

    public function edit(Request $request, string $rule): View
    {
        $context = $this->companyContext($request);

        $record = $this->findRule($context->company->id, $rule);

        return view('chat.automation-rules.edit', [
            'rule' => $record,
            'tags' => WaCustomerTag::where('company_id', $context->company->id)->orderBy('name')->get(),
            'dealStages' => WaDeal::STAGE_LABELS,
            'teamMembers' => $this->companyTeamMembers($context->company),
        ]);
    }

    public function update(Request $request, string $rule): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findRule($context->company->id, $rule);

        $validated = $this->validateRule($request);

        $record->update([
            'name' => $validated['name'],
            'trigger_type' => $validated['trigger_type'],
            'trigger_config' => $this->buildTriggerConfig($validated),
            'action_config' => $this->buildActionConfig($validated),
        ]);

        return redirect()->route('chat.automation-rules.index')->with('success', 'Aturan automasi berhasil diperbarui.');
    }

    public function toggleActive(Request $request, string $rule): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findRule($context->company->id, $rule);
        $record->update(['is_active' => ! $record->is_active]);

        return back()->with('success', $record->is_active ? 'Aturan diaktifkan.' : 'Aturan dinonaktifkan.');
    }

    public function destroy(Request $request, string $rule): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findRule($context->company->id, $rule);
        $record->delete();

        return back()->with('success', 'Aturan automasi berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger_type' => ['required', 'string', 'in:'.implode(',', WaCustomerAutomationRule::TRIGGER_TYPES)],
            'trigger_stage' => ['nullable', 'string', 'in:'.implode(',', WaDeal::STAGES)],
            'trigger_tag_id' => ['nullable', 'uuid'],
            'trigger_days' => ['nullable', 'integer', 'min:1'],
            'action_title' => ['required', 'string', 'max:200'],
            'action_due_in_days' => ['required', 'integer', 'min:0'],
            'action_assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildTriggerConfig(array $validated): array
    {
        return match ($validated['trigger_type']) {
            WaCustomerAutomationRule::TRIGGER_DEAL_STAGE_CHANGED => ['stage' => $validated['trigger_stage'] ?? null],
            WaCustomerAutomationRule::TRIGGER_TAG_ADDED => ['tag_id' => $validated['trigger_tag_id'] ?? null],
            WaCustomerAutomationRule::TRIGGER_NO_CONTACT_DAYS => ['days' => $validated['trigger_days'] ?? null],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildActionConfig(array $validated): array
    {
        return [
            'title' => $validated['action_title'],
            'due_in_days' => $validated['action_due_in_days'],
            'assigned_to' => $validated['action_assigned_to'] ?? null,
        ];
    }

    private function findRule(string $companyId, string $ruleId): WaCustomerAutomationRule
    {
        return WaCustomerAutomationRule::where('company_id', $companyId)
            ->where('id', $ruleId)
            ->firstOrFail();
    }
}
