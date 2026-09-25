<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebFaq;
use App\Support\SortOrder;
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
        $search = $request->string('search')->value();

        // Urut sort_order (bisa diatur dengan tombol naik/turun). Sengaja
        // tidak lewat CrudAdmin::getAll() yang selalu urut created_at desc;
        // route ini sudah di belakang middleware 'superadmin'.
        $faqs = WebFaq::with('user')
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('descriptions', 'like', "%{$search}%")))
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->paginate(50)
            ->withQueryString();

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

        $validated['sort_order'] = SortOrder::next(WebFaq::query());

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

    public function move(string $id, string $direction): RedirectResponse
    {
        SortOrder::move(WebFaq::findOrFail($id), $direction, WebFaq::query());

        return back();
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
