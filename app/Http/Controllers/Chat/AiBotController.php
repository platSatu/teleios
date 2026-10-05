<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaAiBot;
use App\Models\WaAiBotModel;
use App\Models\WaAiBotProvider;
use App\Services\AiBot\KnowledgeBaseExtractor;
use App\Services\Company\CompanyContext;
use App\Services\PackageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 *
 * 1 AI PER CABANG (5 Oktober 2026): lihat saveForDevice().
 */
class AiBotController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        private readonly KnowledgeBaseExtractor $knowledgeBaseExtractor,
        private readonly PackageLimitService $packageLimits,
    ) {
    }

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

        return view('chat.ai-bots.index', compact('bots', 'providers'))
            ->with('isOwner', $context->isOwner);
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

        if ($error = $this->saveForDevice($context, $request, $validated)) {
            return redirect()
                ->route('chat.ai-bots.index')
                ->withErrors(['device_id' => $error], 'newBot')
                ->withInput();
        }

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

        $validator = $this->validator($request, $bot->id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.ai-bots.index')
                ->withErrors($validator, 'editBot'.$id)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['active_bot_immediately'] = $request->boolean('active_bot_immediately');
        $validated['custom_activation_time'] = $request->boolean('custom_activation_time');

        if ($error = $this->saveForDevice($context, $request, $validated, $bot)) {
            return redirect()
                ->route('chat.ai-bots.index')
                ->withErrors(['device_id' => $error], 'editBot'.$id)
                ->withInput();
        }

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
     * record already had, if a new one was uploaded. Also runs the file
     * through KnowledgeBaseExtractor right away and caches the resulting
     * plain text on knowledge_base_text, so App\Services\AiBot\
     * AiReplyGenerator never has to re-parse the source file per message
     * — see that class for how it's actually used. Extraction failing
     * (unsupported quirk, scanned/image-only PDF, etc.) never blocks
     * saving the bot config; it just leaves knowledge_base_text null.
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
        $originalName = $file->getClientOriginalName();
        $storagePath = $file->store('ai-bot-attachments', 'local');

        $validated['attach_file_path'] = $storagePath;
        $validated['attach_file_original_name'] = $originalName;
        $validated['knowledge_base_text'] = $this->knowledgeBaseExtractor->extract($storagePath, $originalName);
        unset($validated['attach_file']); // not a fillable column — only *_path/*_original_name are
    }

    private function validator(Request $request, ?string $ignoreBotId = null)
    {
        return Validator::make($request->all(), [
            // 1 device = 1 AI Bot (juga dijaga unique index di DB). Cabang
            // tidak lagi dari form -- selalu mengikuti device (saveForDevice()).
            'device_id' => [
                'required', 'string', 'max:36',
                \Illuminate\Validation\Rule::unique('wa_ai_bots', 'device_id')->ignore($ignoreBotId),
            ],
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
            'attach_file' => ['nullable', 'file', 'mimes:pdf,txt,docx', 'max:10240'],
            'api_configuration' => ['nullable', 'string'],
            'ai_behaviour_prompt' => ['nullable', 'string'],
            'active_bot_immediately' => ['nullable', 'boolean'],
            'custom_activation_time' => ['nullable', 'boolean'],
            'activation_start_at' => ['required_if:custom_activation_time,1', 'nullable', 'date'],
            'activation_end_at' => ['required_if:custom_activation_time,1', 'nullable', 'date', 'after:activation_start_at'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'device_id.unique' => 'Device ini sudah dipakai AI Bot lain. Satu device hanya bisa memakai 1 AI Bot.',
        ]);
    }

    /**
     * Simpan bot -- dipakai store() & update(). Null = tersimpan, string =
     * pesan error untuk ditampilkan.
     *
     * - Device wajib milik company yang login (staff cabang: cabangnya
     *   sendiri), dicek di server -- device_id dari form tidak dipercaya.
     * - Cabang bot SELALU mengikuti cabang device (null = Pusat).
     * - Jumlah AI per cabang = Package Limit metric "ai_bot" (default 1),
     *   dihitung di dalam transaksi + lock baris company supaya dua simpan
     *   bersamaan tidak menembus batas.
     */
    private function saveForDevice(CompanyContext $context, Request $request, array $validated, ?WaAiBot $bot = null): ?string
    {
        $company = $context->company;
        $device = DB::table('wa_devices')->where('id', $validated['device_id'])->first(['company_id', 'branch_office_id', 'user_id']);

        // Device lama (sebelum ada kolom company_id) dikenali dari user pemilik company.
        $ownsDevice = $device && ($device->company_id
            ? $device->company_id === $company->id
            : $device->user_id === $company->user_id);

        if (! $ownsDevice || (! $context->isOwner && $device->branch_office_id !== $context->branchOffice?->id)) {
            return 'Device ini tidak terdaftar di perusahaan/cabang Anda.';
        }

        $validated['branch_office_id'] = $device->branch_office_id;

        return DB::transaction(function () use ($company, $request, $validated, $bot) {
            Company::whereKey($company->id)->lockForUpdate()->first();

            $branch = $validated['branch_office_id'] ? BranchOffice::find($validated['branch_office_id']) : null;
            $max = $this->packageLimits->limitFor($company, 'ai_bot', $branch)?->max_value ?? 1;

            $used = WaAiBot::where('company_id', $company->id)
                ->where('branch_office_id', $validated['branch_office_id'])
                ->when($bot, fn ($query) => $query->whereKeyNot($bot->id))
                ->count();

            if ($used >= $max) {
                return ($branch ? 'Cabang '.$branch->name : 'Pusat')." sudah punya {$max} AI Bot (batas paket). Edit AI Bot yang sudah ada kalau ingin memindahkan ke device lain.";
            }

            $this->fillLegacyCatalogNames($validated);
            $this->attachFile($request, $validated, $bot);

            $bot ? $bot->update($validated) : WaAiBot::create($validated);

            return null;
        });
    }

}
