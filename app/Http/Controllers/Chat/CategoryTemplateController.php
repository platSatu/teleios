<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaCategoryTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for a company's own WA Template categories (Chat > Pengaturan >
 * Pesan > Kategori Template) — free-form names the company defines
 * itself (e.g. "Promo", "Reminder"), each needing a superadmin's
 * approval (`review_status`) before it's selectable on the WA Template
 * form — see Superadmin\CategoryTemplateReviewController for that side.
 * Always scoped to the logged-in user's own company, same rule as
 * Chat\MessageTemplateController.
 */
class CategoryTemplateController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $categories = WaCategoryTemplate::where('company_id', $company->id)
            ->withCount('templates')
            ->latest()
            ->paginate(15);

        return view('chat.category-templates.index', compact('categories'));
    }

    public function create(): View
    {
        return view('chat.category-templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request, $company->id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.category-templates.create')
                ->withErrors($validator)
                ->withInput();
        }

        WaCategoryTemplate::create([
            'company_id' => $company->id,
            'created_by' => $request->user()?->id,
            'name' => $validator->validated()['name'],
            'status' => 'active',
            'review_status' => 'pending',
        ]);

        return redirect()
            ->route('chat.category-templates.index')
            ->with('success', 'Kategori berhasil diajukan, menunggu review superadmin.');
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $category = WaCategoryTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('chat.category-templates.edit', compact('category'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $category = WaCategoryTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request, $company->id, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.category-templates.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        // Renaming an already-approved (or previously-rejected) category
        // sends it back to the review queue — a superadmin approved (or
        // rejected) specific *text*, not "whatever this row happens to
        // be called later".
        if ($category->name !== $data['name']) {
            $data['review_status'] = 'pending';
            $data['rejection_reason'] = null;
            $data['reviewed_by'] = null;
            $data['reviewed_at'] = null;
        }

        $data['status'] = $request->input('status', 'active');

        $category->update($data);

        return redirect()
            ->route('chat.category-templates.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaCategoryTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.category-templates.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }

    private function validator(Request $request, string $companyId, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($companyId, $ignoreId) {
                    $exists = WaCategoryTemplate::where('company_id', $companyId)
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Kategori dengan nama ini sudah ada.');
                    }
                },
            ],
        ]);
    }
}
