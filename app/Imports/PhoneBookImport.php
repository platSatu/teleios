<?php

namespace App\Imports;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use App\Models\WaPhoneBook;
use App\Services\Crm\CustomerIdentityService;
use App\Services\PackageLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk "Buku Telepon" creation from the Import button on Chat > Buku
 * Telepon — mirrors Chat\PhoneBookController::store() row by row, same
 * shape as App\Imports\CategoryPhoneBookImport/CompanyUsersImport (row
 * cap, per-row error collection, always scoped to the controller-
 * resolved $company/$categories/$branchOffices, never trusts a
 * company/branch/kelompok identifier from the file itself).
 *
 * Runs inside App\Jobs\ProcessPhoneBookImport (queued), not synchronously
 * in the HTTP request that uploaded the file — see that job's docblock.
 */
class PhoneBookImport implements ToCollection, WithHeadingRow
{
    public const MAX_ROWS = 1000;

    /** @var array<int,string> */
    public array $created = [];

    /** @var array<int,array{row:int,name:?string,messages:array<int,string>}> */
    public array $errors = [];

    /**
     * One entry per sheet that had more than MAX_ROWS real (non-blank)
     * data rows — tracked per sheet instead of one global boolean, so a
     * genuinely-too-big sheet is still rejected on its own without
     * discarding the results of every OTHER sheet in the same file that
     * imported fine (see collection()'s docblock for the full reasoning).
     *
     * @var array<int,array{sheet:int,row_count:int}>
     */
    public array $skippedSheets = [];

    /**
     * Which sheet collection() is currently on — Laravel Excel calls
     * collection() once per sheet in the uploaded file when the import
     * class does NOT implement WithMultipleSheets (this one doesn't —
     * see vendor/maatwebsite/excel/src/Reader.php loadSpreadsheet(): the
     * SAME import instance is reused for every sheet in that case), so
     * this instance itself is the only place that can count "which sheet
     * is this". 1-based purely for the user-facing "sheet ke-N" message.
     */
    private int $sheetCallCount = 0;

    /**
     * Package quota guard state (contact_count) — resolved ONCE, here in
     * the constructor before any sheet is processed, rather than
     * re-queried per row: contact_count is a 'stock' metric (see
     * App\Models\LimitMetric::isStock()), so PackageLimitService::
     * assertWithinLimit()'s live-count resolver would otherwise mean one
     * extra COUNT query per row — 1000+ queries for one big import. The
     * running count is instead tracked incrementally in memory
     * ($currentContactCount below) and only ever compared against this,
     * never re-queried — including across multiple sheets in the same
     * file, since both this and the import instance itself persist
     * across every collection() call for the whole file (see
     * $sheetCallCount's docblock). Null means unlimited, same "fail
     * open" convention as the rest of PackageLimitService.
     */
    private ?int $contactCountMax = null;

    private int $currentContactCount = 0;

    /**
     * @param  Collection<int,WaCategoryPhoneBook>  $categories  already scoped to $company (and to the caller's own branch if locked)
     * @param  Collection<int,BranchOffice>  $branchOffices  already scoped the same way
     */
    public function __construct(
        private Company $company,
        private Collection $categories,
        private Collection $branchOffices,
        private ?string $createdBy,
        private ?CustomerIdentityService $customerIdentity = null,
        private ?PackageLimitService $packageLimits = null,
    ) {
        // Nullable + defaulted (rather than a required constructor arg)
        // so this stays constructible without reaching into the
        // container from anywhere that doesn't care about CRM linking /
        // quota enforcement (e.g. a future unit test) — resolve() and
        // the quota check below just skip their step when not provided.
        $this->customerIdentity ??= app(CustomerIdentityService::class);
        $this->packageLimits ??= app(PackageLimitService::class);

        $packageLimit = $this->packageLimits->limitFor($this->company, 'contact_count');
        $this->contactCountMax = $packageLimit?->max_value;
        $this->currentContactCount = WaPhoneBook::where('company_id', $this->company->id)->count();
    }

    /**
     * Called once per sheet in the uploaded file (see $sheetCallCount's
     * docblock) — everything accumulated here ($created/$errors/
     * $skippedSheets/$currentContactCount) carries over ACROSS every
     * call for the same import instance rather than resetting per sheet,
     * so a multi-sheet file (or one with Excel's default blank
     * Sheet2/Sheet3 mixed in) reports one honest combined result instead
     * of only ever reflecting whichever sheet happened to run last.
     */
    public function collection(Collection $rows): void
    {
        $this->sheetCallCount++;

        // Filter out phantom-empty rows BEFORE counting against
        // MAX_ROWS: an .xlsx file's default blank Sheet2/Sheet3 (or a
        // sheet where far more rows than were ever typed into got
        // selected/formatted) can report thousands of "rows" via
        // PhpSpreadsheet's used-range even though not one of them has a
        // name or phone number in it — that used to get misread as "file
        // kelebihan baris" for a sheet that in reality has zero real
        // data. A row only counts as real if it has a name and/or a
        // phone number. Keys are preserved (no ->values()) so $rowNumber
        // below still points at the row's true position in the original
        // file.
        $realRows = $rows->filter(function ($row) {
            $name = trim((string) ($row['nama'] ?? ''));
            $phone = trim((string) ($row['nomor_telepon'] ?? $row['phone'] ?? ''));

            return $name !== '' || $phone !== '';
        });

        if ($realRows->isEmpty()) {
            // Genuinely empty sheet (including a phantom-blank one) —
            // skip silently, this is not an error and not "too many
            // rows".
            return;
        }

        if ($realRows->count() > self::MAX_ROWS) {
            $this->skippedSheets[] = [
                'sheet' => $this->sheetCallCount,
                'row_count' => $realRows->count(),
            ];

            return;
        }

        $categoriesByName = $this->categories->keyBy(fn (WaCategoryPhoneBook $c) => Str::lower($c->name));
        $branchesByName = $this->branchOffices->keyBy(fn (BranchOffice $b) => Str::lower($b->name));
        $seenPhones = [];

        foreach ($realRows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'name' => trim((string) ($row['nama'] ?? '')),
                'phone' => trim((string) ($row['nomor_telepon'] ?? $row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'kelompok' => trim((string) ($row['kelompok'] ?? '')),
                'branch' => trim((string) ($row['branch'] ?? '')),
                'status' => Str::lower(trim((string) ($row['status'] ?? ''))) ?: 'active',
            ];

            $messages = [];

            if ($data['name'] === '') {
                $messages[] = 'Nama wajib diisi.';
            }

            $normalizedPhone = WaPhoneBook::normalizePhone($data['phone']);

            if ($data['phone'] === '' || $normalizedPhone === '') {
                $messages[] = 'Nomor telepon wajib diisi dan valid.';
            } elseif (isset($seenPhones[$normalizedPhone])) {
                $messages[] = "Nomor ini sudah dipakai di baris {$seenPhones[$normalizedPhone]} pada file yang sama.";
            } elseif (WaPhoneBook::where('company_id', $this->company->id)->where('phone', $normalizedPhone)->exists()) {
                $messages[] = 'Nomor ini sudah ada di Buku Telepon.';
            }

            if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $messages[] = 'Format email tidak valid.';
            }

            $category = $data['kelompok'] !== '' ? $categoriesByName->get(Str::lower($data['kelompok'])) : null;

            if ($data['kelompok'] === '') {
                $messages[] = 'Kelompok wajib diisi.';
            } elseif (! $category) {
                $messages[] = "Kelompok \"{$data['kelompok']}\" tidak ditemukan di company ini.";
            }

            $branchOfficeId = null;

            if ($data['branch'] !== '') {
                $branch = $branchesByName->get(Str::lower($data['branch']));

                if (! $branch) {
                    $messages[] = "Branch \"{$data['branch']}\" tidak ditemukan di company ini.";
                } else {
                    $branchOfficeId = $branch->id;
                }
            }

            if (! in_array($data['status'], ['active', 'inactive'], true)) {
                $messages[] = 'Status harus "active" atau "inactive".';
            }

            // Package quota guard (contact_count): checked LAST, only for
            // a row that's otherwise valid and would actually be
            // created — see $contactCountMax's docblock for why this
            // compares against an in-memory running count instead of
            // querying the database again for every row. Once quota is
            // exhausted, every remaining row naturally keeps failing
            // this same check (the counter stops incrementing the
            // moment a row is rejected here), so the rest of the file is
            // reported as failed-with-a-clear-reason instead of being
            // silently dropped or processed past the company's actual
            // limit.
            if ($messages === [] && $this->contactCountMax !== null && $this->currentContactCount + 1 > $this->contactCountMax) {
                $messages[] = 'Kuota kontak perusahaan sudah penuh. Hapus data lama atau upgrade paket untuk menambah kapasitas.';
            }

            if ($messages !== []) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'name' => $data['name'] ?: null,
                    'messages' => $messages,
                ];

                continue;
            }

            $seenPhones[$normalizedPhone] = $rowNumber;

            try {
                // CRM Roadmap Fase 0: same identity-resolve step
                // PhoneBookController::store() does for a single manual
                // add — a bulk import must link its rows exactly the
                // same way a one-off add would, not bypass it.
                $customer = $this->customerIdentity->resolve($this->company->id, $normalizedPhone, [
                    'name' => $data['name'],
                    'branch_office_id' => $branchOfficeId,
                    'created_by' => $this->createdBy,
                ]);

                WaPhoneBook::create([
                    'wa_customer_id' => $customer->id,
                    'company_id' => $this->company->id,
                    'branch_office_id' => $branchOfficeId,
                    'wa_category_phone_book_id' => $category->id,
                    'created_by' => $this->createdBy,
                    'name' => $data['name'],
                    'phone' => $normalizedPhone,
                    'email' => $data['email'] !== '' ? $data['email'] : null,
                    'status' => $data['status'],
                ]);
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'name' => $data['name'],
                    'messages' => ['Gagal menyimpan baris ini, silakan coba lagi.'],
                ];

                continue;
            }

            $this->currentContactCount++;
            $this->created[] = $data['name'].' — '.$normalizedPhone;
        }
    }
}
