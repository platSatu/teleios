<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaAiBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for per-device AI-responder configuration ("AI Bot"). The
 * provider/model list is a fixed placeholder for now — per the user's
 * own note, a real superadmin-managed catalog is a separate follow-up
 * feature, not built here. api_configuration is stored `encrypted` on
 * the model (a tenant's own AI provider API key/config).
 */
class AiBotController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * Placeholder catalog until a superadmin-managed provider/model list
     * exists.
     */
    public const PROVIDERS = ['OpenAI', 'Anthropic (Claude)', 'Google (Gemini)', 'Custom / Lainnya'];

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $bots = WaAiBot::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        $providers = self::PROVIDERS;

        return view('chat.ai-bots.index', compact('bots', 'providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

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

        $this->attachFile($request, $validated);

        WaAiBot::create($validated);

        return redirect()
            ->route('chat.ai-bots.index')
            ->with('success', 'Konfigurasi AI Bot berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $bot = WaAiBot::where('company_id', $company->id)
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

        $this->attachFile($request, $validated, $bot);

        $bot->update($validated);

        return redirect()
            ->route('chat.ai-bots.index')
            ->with('success', 'Konfigurasi AI Bot berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $bot = WaAiBot::where('company_id', $company->id)
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
            'ai_provider' => ['required', 'string', 'max:50'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'attach_file' => ['nullable', 'file', 'max:10240'],
            'api_configuration' => ['nullable', 'string'],
            'ai_behaviour_prompt' => ['nullable', 'string'],
            'active_bot_immediately' => ['nullable', 'boolean'],
            'custom_activation_time' => ['nullable', 'boolean'],
            'activation_start_at' => ['required_if:custom_activation_time,1', 'nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

}
