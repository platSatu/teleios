<?php

namespace App\Imports;

use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\WaCategoryPhoneBook;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk "Kelompok" (wa_category_phone_book) creation from the Import
 * button on Chat > Buku Telepon > Kelompok — mirrors
 * Chat\CategoryPhoneBookController::store() row by row, same shape as
 * App\Imports\CompanyUsersImport (row cap, per-row error collection,
 * always scoped to the controller-resolved $company, never trusts a
 * company/branch identifier from the file itself).
 */
class CategoryPhoneBookImport implements ToCollection, WithHeadingRow
{
    public const MAX_ROWS = 500;

    /** @var array<int,string> */
    public array $created = [];

    /** @var array<int,array{row:int,name:?string,messages:array<int,string>}> */
    public array $errors = [];

    public bool $tooManyRows = false;

    /**
     * @param  Collection<int,BranchOffice>  $branchOffices  already scoped to $company (and to the caller's own branch if locked)
     */
    public function __construct(
        private Company $company,
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

        $branchesByName = $this->branchOffices->keyBy(fn (BranchOffice $b) => Str::lower($b->name));
        $seenNames = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'name' => trim((string) ($row['nama'] ?? '')),
                'branch' => trim((string) ($row['branch'] ?? '')),
                'status' => Str::lower(trim((string) ($row['status'] ?? ''))) ?: 'active',
            ];

            if ($data['name'] === '') {
                continue;
            }

            $messages = [];

            if (isset($seenNames[Str::lower($data['name'])])) {
                $messages[] = "Nama ini sudah dipakai di baris {$seenNames[Str::lower($data['name'])]} pada file yang sama.";
            } elseif (WaCategoryPhoneBook::where('company_id', $this->company->id)
                ->where('name', $data['name'])->exists()) {
                $messages[] = 'Kelompok dengan nama ini sudah ada.';
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

            $seenNames[Str::lower($data['name'])] = $rowNumber;

            try {
                WaCategoryPhoneBook::create([
                    'company_id' => $this->company->id,
                    'branch_office_id' => $branchOfficeId,
                    'created_by' => $this->createdBy,
                    'name' => $data['name'],
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

            $this->created[] = $data['name'];
        }
    }
}
