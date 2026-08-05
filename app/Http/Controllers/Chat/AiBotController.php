<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\WaAiBot;
use App\Models\WaAiBotModel;
use App\Models\WaAiBotProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for per-device AI-responder configuration ("AI Bot"). Provider and
 * model are now picked from the superadmin-managed catalog
 * (App\Models\WaAiBotProvider / WaAiBotModel — see
 * Superadmin\WaAiBotProviderController / WaAiBotModelController) instead
 * of a hardcoded list, and each bot is scoped to the branch it belongs to
 * — same branch-locking pattern as
 * User\Profile\CompanyUserController: a non-owner member can only see/
 * create bots in their own branch, the owner sees and controls every
 * branch. api_configuration is stored `encrypted` on the model (a
 * tenant's own AI provider API key/config).
 */
class AiBotController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $bots = WaAiBot::where('company_id', $company->id)
            ->with(['provider', 'model', 'branchOffice'])
            ->when(! $context->isOwner, fn ($query) => $query->where('branch_office_id', $context->branchOffice?->id))
            ->latest()
            ->paginate(15);

        $providers = $this->activeCatalog();
        $branchOffices = $context->isOwner
            ? BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('chat.ai-bots.index', compact('bots', 'providers', 'branchOffices'))
            ->with('isOwner', $context->isOwner)
            ->with('lockedBranchOffice', $context->branchOffice);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.ai-bots.index')
                ->withErrors($validator, 'newBot')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;
        $validated['active_bot_immediately'] = $request->boolean('active_bot_immediately');
        $validated['custom_activation_time'] = $request->boolean('custom_activation_time');
        $validated['branch_office_id'] = $context->isOwner
            ? ($validated['branch_office_id'] ?? null)
            : $context->branchOffice?->id;

        $this->fillLegacyCatalogNames($validated);
        $this->attachFile($request, $validated);

        WaAiBot::create($validated);

        return redirect()
            ->route('chat.ai-bots.index')
            ->with('success', 'Konfigurasi AI Bot berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $bot = WaAiBot::where('company_id', $company->id)
            ->when(! $context->isOwner, fn ($query) => $query->where('branch_office_id', $context->branchOffice?->id))
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.ai-bots.index')
                ->withErrors($validator, 'editBot'.$id)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['active_bot_immediately'] = $request->boolean('active_bot_immediately');
        $validated['custom_activation_time'] = $request->boolean('custom_activation_time');
        $validated['branch_office_id'] = $context->isOwner
            ? ($validated['branch_office_id'] ?? null)
            : $context->branchOffice?->id;

        $this->fillLegacyCatalogNames($validated);
        $this->attachFile($request, $validated, $bot);

        $bot->update($validated);

        return redirect()
            ->route('chat.ai-bots.index')
            ->with('success', 'Konfigurasi AI Bot berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $bot = WaAiBot::where('company_id', $company->id)
            ->when(! $context->isOwner, fn ($query) => $query->where('branch_office_id', $context->branchOffice?->id))
            ->where('id', $id)
            ->first();

        if (! $bot) {
            abort(404);
        }

        if ($bot->attach_file_path) {
            Storage::disk('local')->delete($bot->attach_file_path);
        }

        $bot->delete();

        return redirect()
            ->route('chat.ai-bots.index')
            ->with('success', 'Konfigurasi AI Bot berhasil dihapus.');
    }

    /**
     * Active providers with their active models, for the dependent
     * Provider -> Model dropdown in the form (see
     * resources/views/chat/ai-bots/_form.blade.php).
     */
    private function activeCatalog()
    {
        return WaAiBotProvider::where('status', 'active')
            ->with(['models' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Mirrors the selected catalog names into the legacy free-text
     * ai_provider/ai_model columns, kept for backward compatibility (see
     * migration 2026_08_05_130200_add_catalog_and_branch_fields_to_wa_ai_bots_table).
     */
    private function fillLegacyCatalogNames(array &$validated): void
    {
        if (! empty($validated['wa_ai_bot_provider_id'])) {
            $validated['ai_provider'] = WaAiBotProvider::find($validated['wa_ai_bot_provider_id'])?->name
                ?? $validated['ai_provider'] ?? null;
        }

        if (! empty($validated['wa_ai_bot_model_id'])) {
            $validated['ai_model'] = WaAiBotModel::find($validated['wa_ai_bot_model_id'])?->name
                ?? $validated['ai_model'] ?? null;
        }
    }

    /**
     * Handles the optional attach_file upload — stored privately (not on
     * the `public` disk) since this may carry a company's internal
     * FAQ/knowledge-base document. Replaces (and deletes) any file the
     * record already had, if a new one was uploaded.
     */
    private function attachFile(Request $request, array &$validated, ?WaAiBot $existing = null): void
    {
        if (! $request->hasFile('attach_file')) {
            return;
        }

        if ($existing && $existing->attach_file_path) {
            Storage::disk('local')->delete($existing->attach_file_path);
        }

        $file = $request->file('attach_file');
        $validated['attach_file_path'] = $file->store('ai-bot-attachments', 'local');
        $validated['attach_file_original_name'] = $file->getClientOriginalName();
        unset($validated['attach_file']); // not a fillable column — only *_path/*_original_name are
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:36'],
            'branch_office_id' => ['nullable', 'uuid', 'exists:branch_offices,id'],
            'wa_ai_bot_provider_id' => [
                'required', 'uuid',
                \Illuminate\Validation\Rule::exists('wa_ai_bot_providers', 'id')->where('status', 'active'),
            ],
            'wa_ai_bot_model_id' => [
                'required', 'uuid',
                \Illuminate\Validation\Rule::exists('wa_ai_bot_models', 'id')
                    ->where('status', 'active')
                    ->where('wa_ai_bot_provider_id', $request->input('wa_ai_bot_provider_id')),
            ],
            'attach_file' => ['nullable', 'file', 'max:10240'],
            'api_configuration' => ['nullable', 'string'],
            'ai_behaviour_prompt' => ['nullable', 'string'],
            'active_bot_immediately' => ['nullable', 'boolean'],
            'custom_activation_time' => ['nullable', 'boolean'],
            'activation_start_at' => ['required_if:custom_activation_time,1', 'nullable', 'date'],
            'activation_end_at' => ['required_if:custom_activation_time,1', 'nullable', 'date', 'after:activation_start_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

}
