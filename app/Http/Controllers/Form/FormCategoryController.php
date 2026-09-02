<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\FormCategory;
use App\Models\FormContent;
use App\Models\FormFooter;
use App\Models\FormHeader;
use App\Models\FormSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD untuk Form Category (level ke-2 drill-down fitur Form, di bawah
 * Branch). Branch scoping mengikuti pola App\Http\Controllers\Jadwal\
 * JadwalMataPelajaranController persis -- bedanya branch_office_id di
 * sini WAJIB (tidak nullable), jadi tidak ada cabang "orWhereNull".
 */
class FormCategoryController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = FormCategory::where('company_id', $company->id)
            ->withCount('headers')
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        } elseif ($branchOfficeId) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $categories = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        $branch = $branchOfficeId
            ? BranchOffice::where('company_id', $company->id)->where('id', $branchOfficeId)->first()
            : null;

        return view('form.form-category.index', compact('categories', 'branch', 'branchOfficeId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('form.form-category.create', [
            'category' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id'),
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('form.category.create', array_filter(['branch_office_id' => $request->input('branch_office_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        FormCategory::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? FormCategory::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.category.index', ['branch_office_id' => $validated['branch_office_id']])
            ->with('success', 'Form Category berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);

        return view('form.form-category.edit', [
            'category' => $category,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $category = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('form.category.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $category->update([
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? FormCategory::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.category.index', ['branch_office_id' => $category->branch_office_id])
            ->with('success', 'Form Category berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);
        $branchOfficeId = $category->branch_office_id;

        // cascadeOnDelete di migration sudah membersihkan header/content/
        // footer/setting/submission turunannya di level DB.
        $category->delete();

        return redirect()
            ->route('form.category.index', ['branch_office_id' => $branchOfficeId])
            ->with('success', 'Form Category berhasil dihapus.');
    }

    /**
     * Tombol "Copy" di index -- deep-clone SATU Form Category beserta
     * seluruh rangkaian di bawahnya (tiap header -> content/footer/
     * setting-nya) jadi Form Category baru. Hasil copy sengaja dibuat
     * INACTIVE (category & tiap header-nya) supaya tidak tiba-tiba live
     * ke publik sebelum admin sempat cek ulang. Submission (histori
     * isian) TIDAK ikut ter-copy -- itu bukan bagian dari desain form.
     */
    public function duplicate(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $original = $this->findOrFail($context, $id);
        $original->load(['headers.contents', 'headers.footers', 'headers.setting']);

        DB::transaction(function () use ($original, $company) {
            $newCategory = FormCategory::create([
                'company_id' => $company->id,
                'branch_office_id' => $original->branch_office_id,
                'name' => $original->name.' (Copy)',
                'status' => FormCategory::STATUS_INACTIVE,
            ]);

            foreach ($original->headers as $header) {
                $newHeader = FormHeader::create([
                    'company_id' => $company->id,
                    'branch_office_id' => $original->branch_office_id,
                    'form_category_id' => $newCategory->id,
                    'name' => $header->name,
                    'slug' => FormHeaderController::generateUniqueSlug($header->name),
                    'background' => $header->background,
                    'description' => $header->description,
                    'start_date' => $header->start_date,
                    'end_date' => $header->end_date,
                    'status' => FormHeader::STATUS_INACTIVE,
                ]);

                foreach ($header->contents as $content) {
                    FormContent::create([
                        'company_id' => $company->id,
                        'branch_office_id' => $original->branch_office_id,
                        'form_category_id' => $newCategory->id,
                        'form_header_id' => $newHeader->id,
                        'name' => $content->name,
                        'type' => $content->type,
                        'options' => $content->options,
                        'allowed_file_types' => $content->allowed_file_types,
                        'is_required' => $content->is_required,
                        'position' => $content->position,
                    ]);
                }

                foreach ($header->footers as $footer) {
                    FormFooter::create([
                        'company_id' => $company->id,
                        'branch_office_id' => $original->branch_office_id,
                        'form_category_id' => $newCategory->id,
                        'form_header_id' => $newHeader->id,
                        'name' => $footer->name,
                        'status' => $footer->status,
                    ]);
                }

                if ($header->setting) {
                    FormSetting::create([
                        'company_id' => $company->id,
                        'branch_office_id' => $original->branch_office_id,
                        'form_category_id' => $newCategory->id,
                        'form_header_id' => $newHeader->id,
                        'device_id' => $header->setting->device_id,
                        'notify_wa_enabled' => $header->setting->notify_wa_enabled,
                        'wa_message_template_id' => $header->setting->wa_message_template_id,
                        'additional_info' => $header->setting->additional_info,
                        'status' => $header->setting->status,
                    ]);
                }
            }
        });

        return redirect()
            ->route('form.category.index', ['branch_office_id' => $original->branch_office_id])
            ->with('success', 'Form Category berhasil diduplikasi.');
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): FormCategory
    {
        $query = FormCategory::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($company, $ignoreId, $request) {
                    $exists = FormCategory::where('company_id', $company->id)
                        ->where('branch_office_id', $request->input('branch_office_id'))
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Form Category dengan nama ini sudah ada di branch tersebut.');
                    }
                },
            ],
            'branch_office_id' => [
                'required', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'status' => ['nullable', 'in:'.FormCategory::STATUS_ACTIVE.','.FormCategory::STATUS_INACTIVE],
        ]);
    }
}
