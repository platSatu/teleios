<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\WaAiBotProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for App\Models\WaAiBotProvider — the catalog of AI
 * providers offered on the "AI Bot" tab (App\Http\Controllers\Chat\
 * AiBotController), e.g. "OpenAI (ChatGPT)", "Google (Gemini)". This is
 * "menentukan apa yang bisa diajak kerja sama atau tidak" — switching a
 * provider to 'inactive' here removes it from every company's dropdown
 * platform-wide without touching any company's existing configuration
 * that already points at it (see WaAiBotProvider's docblock). All data
 * access goes through CrudAdmin (app/Helpers/CrudAdmin.php), which
 * enforces the superadmin-only guard and writes every store/update/
 * delete to the audit_logs table — this controller itself does no
 * authorization or auditing of its own.
 */
class WaAiBotProviderController extends Controller
{
    public function index(Request $request): View
    {
        $providers = CrudAdmin::getAll(
            modelClass: WaAiBotProvider::class,
            relations: ['models'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name'],
        );

        return view('superadmin.wa-ai-bot-provider.index', compact('providers'));
    }

    public function create(): View
    {
        return view('superadmin.wa-ai-bot-provider.create');
    }

    public function store(Request $request): RedirectResponse
    {
        CrudAdmin::store(WaAiBotProvider::class, $this->validated($request));

        return redirect()
            ->route('wa-ai-bot-provider.index')
            ->with('success', 'Provider AI berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $provider = CrudAdmin::find(WaAiBotProvider::class, $id);

        return view('superadmin.wa-ai-bot-provider.edit', compact('provider'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(WaAiBotProvider::class, $id, $this->validated($request));

        return redirect()
            ->route('wa-ai-bot-provider.index')
            ->with('success', 'Provider AI berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WaAiBotProvider::class, $id);

        return redirect()
            ->route('wa-ai-bot-provider.index')
            ->with('success', 'Provider AI berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
