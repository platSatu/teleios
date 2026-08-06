<?php

namespace App\Imports;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use App\Models\WaPhoneBook;
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
 */
class PhoneBookImport implements ToCollection, WithHeadingRow
{
    public const MAX_ROWS = 1000;

    /** @var array<int,string> */
    public array $created = [];

    /** @var array<int,array{row:int,name:?string,messages:array<int,string>}> */
    public array $errors = [];

    public bool $tooManyRows = false;

    /**
     * @param  Collection<int,WaCategoryPhoneBook>  $categories  already scoped to $company (and to the caller's own branch if locked)
     * @param  Collection<int,BranchOffice>  $branchOffices  already scoped the same way
     */
    public function __construct(
        private Company $company,
        private Collection $categories,
        private Collection $branchOffices,
        private ?string $createdBy,
    ) {
    }

    public function collection(Collection $rows): void
    {
        if ($rows->count() > self::MAX_ROWS) {
            $this->tooManyRows = true;

            return;
        }

        $categoriesByName = $this->categories->keyBy(fn (WaCategoryPhoneBook $c) => Str::lower($c->name));
        $branchesByName = $this->branchOffices->keyBy(fn (BranchOffice $b) => Str::lower($b->name));
        $seenPhones = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'name' => trim((string) ($row['nama'] ?? '')),
                'phone' => trim((string) ($row['nomor_telepon'] ?? $row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'kelompok' => trim((string) ($row['kelompok'] ?? '')),
                'branch' => trim((string) ($row['branch'] ?? '')),
                'status' => Str::lower(trim((string) ($row['status'] ?? ''))) ?: 'active',
            ];

            if ($data['name'] === '' && $data['phone'] === '') {
                continue;
            }

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
                WaPhoneBook::create([
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

            $this->created[] = $data['name'].' — '.$normalizedPhone;
        }
    }
}
