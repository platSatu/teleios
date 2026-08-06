<?php

namespace App\Http\Controllers\Chat;

use App\Exports\PhoneBookImportTemplateExport;
use App\Exports\PhoneBooksExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Imports\PhoneBookImport;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use App\Models\WaPhoneBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CRUD for a company's "Buku Telepon" (Chat > Buku Telepon > Kontak) —
 * see App\Models\WaPhoneBook's docblock for how this differs from the
 * auto-populated App\Models\WaContact CRM book. Every entry belongs to
 * exactly one App\Models\WaCategoryPhoneBook "Kelompok"; branch scoping
 * mirrors Chat\ContactController/Chat\CategoryPhoneBookController.
 *
 * Blacklist (blacklist()/unblacklist()) is a status flag on the same
 * row rather than a separate resource — see the migration's docblock —
 * so it lives here instead of its own controller.
 */
class PhoneBookController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = WaPhoneBook::where('company_id', $company->id)
            ->with(['category:id,name', 'branchOffice:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('wa_category_phone_book_id')) {
            $query->where('wa_category_phone_book_id', $request->query('wa_category_phone_book_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->query('blacklist') === '1') {
            $query->where('is_blacklisted', true);
        } elseif ($request->query('blacklist') === '0') {
            $query->where('is_blacklisted', false);
        }

        $phoneBooks = $query->latest()->paginate(20)->withQueryString();

        $categories = WaCategoryPhoneBook::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('chat.phone-books.index', compact('phoneBooks', 'categories'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('chat.phone-books.create', [
            'phoneBook' => null,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
            'categories' => $this->categoriesFor($context->company, $context),
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
                ->route('chat.phone-books.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        WaPhoneBook::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'wa_category_phone_book_id' => $validated['wa_category_phone_book_id'],
            'created_by' => $request->user()?->id,
            'name' => $validated['name'],
            'phone' => WaPhoneBook::normalizePhone($validated['phone']),
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', 'Kontak berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $phoneBook = $this->findOrFail($context, $id);

        return view('chat.phone-books.edit', [
            'phoneBook' => $phoneBook,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
            'categories' => $this->categoriesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $phoneBook = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $phoneBook->id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.phone-books.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $phoneBook->update([
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'wa_category_phone_book_id' => $validated['wa_category_phone_book_id'],
            'name' => $validated['name'],
            'phone' => WaPhoneBook::normalizePhone($validated['phone']),
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $phoneBook = $this->findOrFail($context, $id);
        $phoneBook->delete();

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', 'Kontak berhasil dihapus.');
    }

    /**
     * A blacklisted entry stays in the phone book (so it can be reversed)
     * but every recipient picker across Chat should skip it — see
     * WaPhoneBook's migration docblock.
     */
    public function blacklist(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $phoneBook = $this->findOrFail($context, $id);

        $reason = trim((string) $request->input('reason', ''));

        $phoneBook->update([
            'is_blacklisted' => true,
            'blacklist_reason' => $reason !== '' ? $reason : null,
            'blacklisted_at' => now(),
        ]);

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', 'Kontak berhasil dimasukkan ke blacklist.');
    }

    public function unblacklist(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $phoneBook = $this->findOrFail($context, $id);

        $phoneBook->update([
            'is_blacklisted' => false,
            'blacklist_reason' => null,
            'blacklisted_at' => null,
        ]);

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', 'Kontak berhasil dikeluarkan dari blacklist.');
    }

    /**
     * "Export" button — every phone book entry the caller can see, as an
     * .xlsx download. See App\Exports\PhoneBooksExport.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = WaPhoneBook::where('company_id', $company->id)
            ->with(['category:id,name', 'branchOffice:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $filename = 'buku-telepon-'.$company->slug.'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new PhoneBooksExport($query), $filename);
    }

    public function importTemplate(Request $request): BinaryFileResponse
    {
        $context = $this->companyContext($request);

        $export = new PhoneBookImportTemplateExport(
            $this->categoriesFor($context->company, $context)->pluck('name'),
            $this->branchOfficesFor($context->company, $context)->pluck('name'),
        );

        return Excel::download($export, 'template-import-buku-telepon.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.phone-books.index')
                ->withErrors($validator);
        }

        $import = new PhoneBookImport(
            $company,
            $this->categoriesFor($company, $context),
            $this->branchOfficesFor($company, $context),
            $request->user()?->id,
        );

        Excel::import($import, $request->file('file'));

        if ($import->tooManyRows) {
            return redirect()
                ->route('chat.phone-books.index')
                ->with('error', 'File terlalu banyak baris (maksimal '.PhoneBookImport::MAX_ROWS.' baris per import). Tidak ada data yang disimpan.');
        }

        return redirect()
            ->route('chat.phone-books.index')
            ->with('importResult', [
                'created' => $import->created,
                'errors' => $import->errors,
            ]);
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    /**
     * A branch-locked member may only file a contact under a Kelompok
     * that's either unassigned to any branch or their own branch — same
     * "own branch or untriaged" rule as findOrFail()/index() below.
     */
    private function categoriesFor(Company $company, $context)
    {
        $query = WaCategoryPhoneBook::where('company_id', $company->id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): WaPhoneBook
    {
        $query = WaPhoneBook::where('company_id', $context->company->id)
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:32',
                function ($attribute, $value, $fail) use ($company, $ignoreId) {
                    $normalized = WaPhoneBook::normalizePhone($value);

                    if ($normalized === '') {
                        $fail('Nomor telepon tidak valid.');

                        return;
                    }

                    $exists = WaPhoneBook::where('company_id', $company->id)
                        ->where('phone', $normalized)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Nomor ini sudah ada di Buku Telepon.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'wa_category_phone_book_id' => [
                'required', 'uuid',
                function ($attribute, $value, $fail) use ($company) {
                    if (! WaCategoryPhoneBook::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Kelompok tidak valid.');
                    }
                },
            ],
            'branch_office_id' => [
                'nullable', 'uuid',
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
