<?php

namespace App\Http\Controllers\Chat;

use App\Exports\CategoryPhoneBookImportTemplateExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Imports\CategoryPhoneBookImport;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CRUD for a company's "Kelompok" (Chat > Buku Telepon > Kelompok) —
 * groups used to organize wa_phone_book entries and as a pickable
 * recipient group elsewhere in Chat. Branch scoping follows the same
 * rule as Chat\ContactController: an owner sees/manages every group, a
 * branch-locked member only their own branch's (plus any group with no
 * branch set, since those aren't tied to one branch specifically).
 */
class CategoryPhoneBookController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = WaCategoryPhoneBook::where('company_id', $company->id)
            ->withCount('phoneBooks')
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $categories = $query->latest()->paginate(15)->withQueryString();

        return view('chat.category-phone-books.index', compact('categories'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('chat.category-phone-books.create', [
            'category' => null,
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
                ->route('chat.category-phone-books.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        WaCategoryPhoneBook::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'created_by' => $request->user()?->id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('chat.category-phone-books.index')
            ->with('success', 'Kelompok berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);

        return view('chat.category-phone-books.edit', [
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
                ->route('chat.category-phone-books.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $category->update([
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('chat.category-phone-books.index')
            ->with('success', 'Kelompok berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);
        $category->delete();

        return redirect()
            ->route('chat.category-phone-books.index')
            ->with('success', 'Kelompok berhasil dihapus.');
    }

    /**
     * "Download Template" inside the Import modal. See
     * App\Exports\CategoryPhoneBookImportTemplateExport.
     */
    public function importTemplate(Request $request): BinaryFileResponse
    {
        $context = $this->companyContext($request);

        $export = new CategoryPhoneBookImportTemplateExport(
            $this->branchOfficesFor($context->company, $context)->pluck('name')
        );

        return Excel::download($export, 'template-import-kelompok.xlsx');
    }

    /**
     * "Import" button — bulk-create groups from an uploaded .xlsx/.csv
     * file. See App\Imports\CategoryPhoneBookImport's docblock.
     */
    public function import(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.category-phone-books.index')
                ->withErrors($validator);
        }

        $import = new CategoryPhoneBookImport(
            $company,
            $this->branchOfficesFor($company, $context),
            $request->user()?->id,
        );

        Excel::import($import, $request->file('file'));

        if ($import->tooManyRows) {
            return redirect()
                ->route('chat.category-phone-books.index')
                ->with('error', 'File terlalu banyak baris (maksimal '.CategoryPhoneBookImport::MAX_ROWS.' baris per import). Tidak ada data yang disimpan.');
        }

        return redirect()
            ->route('chat.category-phone-books.index')
            ->with('importResult', [
                'created' => $import->created,
                'errors' => $import->errors,
            ]);
    }

    /**
     * Branch-locked members only ever get their own branch to pick from
     * (no picker at all, effectively forced) — same rule
     * CompanyUserController::create() applies for placing a new member.
     */
    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): WaCategoryPhoneBook
    {
        $query = WaCategoryPhoneBook::where('company_id', $context->company->id)
            ->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($company, $ignoreId) {
                    $exists = WaCategoryPhoneBook::where('company_id', $company->id)
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Kelompok dengan nama ini sudah ada.');
                    }
                },
            ],
            // exists: alone only checks the row is real, not that it
            // belongs to THIS company — the closure below closes that
            // gap, same "never trust a foreign id belongs to the caller's
            // own company just because it exists somewhere" rule as
            // CompanyUserController::validateBranchOfficeAndUnit().
            'branch_office_id' => [
                'nullable', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
