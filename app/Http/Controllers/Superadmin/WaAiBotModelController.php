<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\WaAiBotModel;
use App\Models\WaAiBotProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for App\Models\WaAiBotModel — which model names exist
 * under each App\Models\WaAiBotProvider (e.g. "OpenAI (ChatGPT)" ->
 * gpt-4o, gpt-4-turbo). Every entry is required to belong to exactly one
 * provider, same "every menu belongs to one category" rule as
 * Superadmin\ApplicationMenuController. All data access goes through
 * CrudAdmin (app/Helpers/CrudAdmin.php), which enforces the
 * superadmin-only guard and writes every store/update/delete to the
 * audit_logs table.
 */
class WaAiBotModelController extends Controller
{
    public function index(Request $request): View
    {
        $models = CrudAdmin::getAll(
            modelClass: WaAiBotModel::class,
            relations: ['provider'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name'],
        );

        return view('superadmin.wa-ai-bot-model.index', compact('models'));
    }

    public function create(): View
    {
        $providers = WaAiBotProvider::orderBy('name')->get(['id', 'name']);

        return view('superadmin.wa-ai-bot-model.create', compact('providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        CrudAdmin::store(WaAiBotModel::class, $this->validated($request));

        return redirect()
            ->route('wa-ai-bot-model.index')
            ->with('success', 'Model AI berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $model = CrudAdmin::find(WaAiBotModel::class, $id);
        $providers = WaAiBotProvider::orderBy('name')->get(['id', 'name']);

        return view('superadmin.wa-ai-bot-model.edit', compact('model', 'providers'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(WaAiBotModel::class, $id, $this->validated($request));

        return redirect()
            ->route('wa-ai-bot-model.index')
            ->with('success', 'Model AI berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WaAiBotModel::class, $id);

        return redirect()
            ->route('wa-ai-bot-model.index')
            ->with('success', 'Model AI berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'wa_ai_bot_provider_id' => ['required', 'uuid', 'exists:wa_ai_bot_providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
