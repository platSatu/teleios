<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebFaq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for FAQ entries (App\Models\WebFaq) — flat list, no
 * category, no slug/image. Same shape as Superadmin\CategoryApplicationController —
 * all data access goes through CrudAdmin.
 */
class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = CrudAdmin::getAll(
            modelClass: WebFaq::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'descriptions'],
        );

        return view('superadmin.web.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.faqs.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(WebFaq::class, $validated);

        return redirect()
            ->route('web.faqs.index')
            ->with('success', 'FAQ berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $faq = CrudAdmin::find(WebFaq::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.faqs.edit', compact('faq', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(WebFaq::class, $id, $validated);

        return redirect()
            ->route('web.faqs.index')
            ->with('success', 'FAQ berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebFaq::class, $id);

        return redirect()
            ->route('web.faqs.index')
            ->with('success', 'FAQ berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
