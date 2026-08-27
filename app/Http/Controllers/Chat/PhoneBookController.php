<?php

namespace App\Http\Controllers\Chat;

use App\Exceptions\PackageLimitExceededException;
use App\Exports\PhoneBookImportTemplateExport;
use App\Exports\PhoneBooksExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPhoneBookImport;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use App\Models\WaPhoneBook;
use App\Models\WaPhoneBookImport;
use App\Services\Crm\CustomerIdentityService;
use App\Services\PackageLimitService;
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

    public function __construct(
        protected CustomerIdentityService $customerIdentity,
        protected PackageLimitService $packageLimits,
    ) {
    }

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

        // 10/page + a tighter pagination window (onEachSide) to match
        // Pesan Terjadwal and Google Form's list pages — was 20/page with
        // Laravel's default onEachSide=3, which read as a wall of page
        // number links on a table with hundreds of rows.
        $phoneBooks = $query->latest()->paginate(10)->withQueryString()->onEachSide(1);

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

        // Package quota guard: "contact_count" is a 'stock' metric (see
        // App\Models\LimitMetric) — measured live against the real
        // current count rather than a separately-tracked counter, so it
        // can't drift if rows get deleted/imported outside this method.
        // Fails open if there's no active package or the package doesn't
        // cap this metric.
        try {
            $this->packageLimits->assertWithinLimit(
                $company,
                'contact_count',
                1,
                null,
                fn () => WaPhoneBook::where('company_id', $company->id)->count(),
            );
        } catch (PackageLimitExceededException $e) {
            return redirect()
                ->route('chat.phone-books.create')
                ->withErrors(['limit' => $e->getMessage()])
                ->withInput();
        }

        $validated = $validator->validated();
        $phone = WaPhoneBook::normalizePhone($validated['phone']);

        // CRM Roadmap Fase 0: resolve (or create) the one WaCustomer
        // identity this phone belongs to before creating the phone book
        // row, so it links from the moment it's created — same pattern
        // InboxController::contact() uses for WaContact. See
        // App\Services\Crm\CustomerIdentityService's docblock.
        $customer = $this->customerIdentity->resolve($company->id, $phone, [
            'name' => $validated['name'],
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        WaPhoneBook::create([
            'wa_customer_id' => $customer->id,
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'wa_category_phone_book_id' => $validated['wa_category_phone_book_id'],
            'created_by' => $request->user()?->id,
            'name' => $validated['name'],
            'phone' => $phone,
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
        $phone = WaPhoneBook::normalizePhone($validated['phone']);

        // Phone is editable here (unlike WaContact, which never lets its
        // phone change) — re-resolve in case it changed, so this row
        // stays linked to the customer identity matching whatever phone
        // it ends up with. A no-op find when the phone didn't change.
        $customer = $this->customerIdentity->resolve($company->id, $phone, [
            'name' => $validated['name'],
            'branch_office_id' => $validated['branch_office_id'] ?? null,
        ]);

        $phoneBook->update([
            'wa_customer_id' => $customer->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'wa_category_phone_book_id' => $validated['wa_category_phone_book_id'],
            'name' => $validated['name'],
            'phone' => $phone,
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
     * "Hapus Semua Kontak" — hapus PERMANEN seluruh Buku Telepon yang bisa
     * dilihat caller. Scoping-nya sama persis dengan index()/destroy():
     * company ini, dan kalau caller branch-locked cuma yang jadi
     * miliknya sendiri atau belum ditugaskan ke branch manapun — jadi
     * seorang branch-locked member tidak bisa menghapus kontak branch
     * lain lewat endpoint ini, walaupun namanya "reset ALL".
     *
     * WaPhoneBook tidak pakai SoftDeletes (lihat model), dan tidak ada FK
     * dari tabel lain yang menunjuk ke wa_phone_book (sudah dicek lewat
     * migrations), jadi query delete() langsung ini aman secara
     * integritas data — tapi tetap tidak bisa dibatalkan begitu jalan.
     */
    public function resetAll(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = WaPhoneBook::where('company_id', $company->id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $deleted = $query->delete();

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', "Berhasil menghapus {$deleted} kontak dari Buku Telepon.");
    }

    /**
     * "Hapus Kontak di Kelompok Ini" — sama seperti resetAll() tapi
     * dipersempit ke satu Kelompok (Kategori) tertentu. Kelompoknya
     * sendiri divalidasi dulu (company + branch scope, sama pola dengan
     * categoriesFor()) sebelum isinya dihapus — supaya ID kelompok milik
     * company lain tidak bisa dipakai buat menghapus data company lain
     * (IDOR) lewat route ini.
     */
    public function resetByCategory(Request $request, string $categoryId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $categoryQuery = WaCategoryPhoneBook::where('company_id', $company->id)
            ->where('id', $categoryId);

        if ($context->isLockedToBranch()) {
            $categoryQuery->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $category = $categoryQuery->firstOrFail();

        $query = WaPhoneBook::where('company_id', $company->id)
            ->where('wa_category_phone_book_id', $category->id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $deleted = $query->delete();

        return redirect()
            ->route('chat.phone-books.index')
            ->with('success', "Berhasil menghapus {$deleted} kontak dari kelompok \"{$category->name}\".");
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

    /**
     * Upload only — the actual parsing/inserting happens off the
     * request, in App\Jobs\ProcessPhoneBookImport, so a ~1000-row file
     * can't time out the request and its result can't be lost the
     * moment a one-shot session flash expires. See
     * App\Models\WaPhoneBookImport's migration docblock for the full
     * reasoning.
     */
    public function import(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = Validator::make($request->all(), [
            // 5MB (was 2MB) — ~1000 contact rows with a few extra
            // columns can get close to 2MB depending on formatting, so
            // this leaves real headroom rather than rejecting a
            // legitimately-sized file.
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.phone-books.index')
                ->withErrors($validator);
        }

        // Private disk — this file is only ever read back by
        // ProcessPhoneBookImport, never served to a browser, same
        // "private input file for a background process" pattern
        // Chat\AiBotController::attachFile() uses for attach_file_path.
        // The job deletes it once processed either way (success or
        // failure) — see ProcessPhoneBookImport::handle().
        $path = $request->file('file')->store('phone-book-imports', 'local');

        $import = WaPhoneBookImport::create([
            'company_id' => $company->id,
            'user_id' => $request->user()?->id,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            // Snapshot, taken NOW while $context is still available, of
            // exactly which Kelompok/branch rows the uploading user is
            // allowed to import against — see
            // App\Jobs\ProcessPhoneBookImport's docblock for why the
            // queued job re-fetches by these ids instead of re-deriving
            // a branch-locked member's scope itself.
            'allowed_category_ids' => $this->categoriesFor($company, $context)->pluck('id')->all(),
            'allowed_branch_office_ids' => $this->branchOfficesFor($company, $context)->pluck('id')->all(),
            'status' => WaPhoneBookImport::STATUS_PENDING,
        ]);

        ProcessPhoneBookImport::dispatch($import->id);

        return redirect()
            ->route('chat.phone-books.import-history')
            ->with('success', 'Import sedang diproses di background. Hasilnya akan muncul di halaman ini begitu selesai — refresh halaman ini setelah beberapa saat kalau statusnya masih "Diproses".');
    }

    /**
     * "Riwayat Import" — every App\Models\WaPhoneBookImport this
     * company has run, newest first, each showing its own
     * created/errors/skipped-sheets result (or a failure_message, or
     * "still processing") once the background job reaches it. Always
     * scoped to company_id only (an import itself isn't tied to a
     * branch — the branch scoping that mattered happened once, at
     * upload time, via allowed_category_ids/allowed_branch_office_ids —
     * see import() above), same multi-tenant rule every other list in
     * this controller follows.
     */
    public function importHistory(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $imports = WaPhoneBookImport::where('company_id', $company->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('chat.phone-books.import-history', compact('imports'));
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
