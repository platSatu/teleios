<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\AppliesTemplateModeration;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaCategoryTemplate;
use App\Services\Moderation\TemplateModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for a company's own WA Template categories (Chat > Pengaturan >
 * Pesan > Kategori Template) — free-form names the company defines
 * itself (e.g. "Promo", "Reminder").
 *
 * `review_status` is no longer a manual superadmin approval gate — every
 * create/rename is run through App\Services\Moderation\
 * TemplateModerationService (the superadmin-configured AI, see
 * App\Models\AiModerationSetting) instead, which can approve the name
 * as-is, silently correct it, or reject it with a reason. There is no
 * more human-approval queue on the superadmin side for this resource
 * (see Superadmin\WaTemplateReviewController, now read-only oversight).
 * Always scoped to the logged-in user's own company, same rule as
 * Chat\MessageTemplateController.
 */
class CategoryTemplateController extends Controller
{
    use ResolvesCompanyContext, AppliesTemplateModeration;

    public function __construct(protected TemplateModerationService $moderation)
    {
    }

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

        $name = $validator->validated()['name'];
        $moderation = $this->moderation->moderate(['name' => $name]);

        if ($moderation->isCorrected()) {
            $name = $moderation->fields['name'] ?? $name;
        }

        WaCategoryTemplate::create(array_merge([
            'company_id' => $company->id,
            'created_by' => $request->user()?->id,
            'name' => $name,
            'status' => 'active',
        ], $this->reviewFieldsFor($moderation)));

        [$flashType, $flashMessage] = $this->flashFor(
            $moderation,
            'Kategori berhasil dibuat',
            "nama disesuaikan otomatis menjadi \"{$name}\""
        );

        return redirect()
            ->route('chat.category-templates.index')
            ->with($flashType, $flashMessage);
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
        $data['status'] = $request->input('status', 'active');

        $flashType = 'success';
        $flashMessage = 'Kategori berhasil diperbarui.';

        // Renaming an already-approved (or previously-rejected) category
        // sends it back through moderation — an AI approved (or
        // rejected) specific *text*, not "whatever this row happens to
        // be called later".
        if ($category->name !== $data['name']) {
            $moderation = $this->moderation->moderate(['name' => $data['name']]);

            if ($moderation->isCorrected()) {
                $data['name'] = $moderation->fields['name'] ?? $data['name'];
            }

            $data = array_merge($data, $this->reviewFieldsFor($moderation));
            [$flashType, $flashMessage] = $this->flashFor(
                $moderation,
                'Kategori berhasil diperbarui',
                "nama disesuaikan otomatis menjadi \"{$data['name']}\""
            );
        }

        $category->update($data);

        return redirect()
            ->route('chat.category-templates.index')
            ->with($flashType, $flashMessage);
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
