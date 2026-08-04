<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyToUser;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk "Setting Users" creation from the Import modal on
 * dashboard/user/profile — mirrors User\Profile\CompanyUserController::
 * store() row by row instead of accepting one member per HTTP request.
 * See that controller's docblock for the company_to_users-one-row-per-
 * category modeling; this class follows the exact same rule (one row
 * created per category name in the comma-separated `category_aplikasi`
 * cell).
 *
 * Security posture (all deliberate, not incidental):
 *  - Row count capped at self::MAX_ROWS — the WHOLE file is rejected
 *    (nothing created) rather than silently truncated, so an oversized
 *    file can't quietly leave the company half-imported.
 *  - `role` is resolved by name but only ever looked up inside
 *    $companyRoles, which the controller already scoped to this one
 *    company — a row can never attach a member to another company's
 *    role.
 *  - `category_aplikasi` names are resolved only against
 *    $activeCategoryApplications — the SAME "does the owner currently
 *    have an active package for this category" set
 *    CompanyUserController::store()/update() enforce (see
 *    ScopesActivePackage), so bulk import can't grant access to a
 *    category the owner never paid for.
 *  - Every email is checked against `users` AND against every other row
 *    already seen earlier in this same file, so duplicate rows can't
 *    create two accounts for the same address.
 *  - A blank `password` cell gets a random 16-character generated
 *    password (Str::password() — never a predictable default), surfaced
 *    back to the owner via $this->created so they can hand it to the
 *    new member out of band.
 *  - Each row is created inside its own DB transaction — one bad row
 *    can't roll back the rows before/after it, and a row that fails
 *    partway through (e.g. mid category loop) can't leave an orphaned
 *    User with no company_to_users rows.
 *  - The uploaded file itself is never stored on disk — Excel::import()
 *    reads it straight from the request's temp upload, which PHP
 *    discards the normal way at the end of the request.
 *  - No row here is ever trusted to identify ITS OWN company — $company
 *    always comes from the controller's already-scoped
 *    ownedCompanyOrFail(), never from a file column.
 */
class CompanyUsersImport implements ToCollection, WithHeadingRow
{
    public const MAX_ROWS = 500;

    /** @var array<int,array{name:string,email:string,password:?string}> */
    public array $created = [];

    /** @var array<int,array{row:int,email:?string,messages:array<int,string>}> */
    public array $errors = [];

    public bool $tooManyRows = false;

    /**
     * @param  Collection<int,CompanyRole>  $companyRoles  already scoped to $company
     * @param  Collection<int,\App\Models\CategoryApplication>  $activeCategoryApplications  already scoped to the owner's active package(s)
     */
    public function __construct(
        private Company $company,
        private Collection $companyRoles,
        private Collection $activeCategoryApplications,
    ) {
    }

    public function collection(Collection $rows): void
    {
        if ($rows->count() > self::MAX_ROWS) {
            $this->tooManyRows = true;

            return;
        }

        $rolesByName = $this->companyRoles->keyBy(fn ($role) => Str::lower($role->name));
        $categoriesByName = $this->activeCategoryApplications->keyBy(fn ($category) => Str::lower($category->name));

        $seenEmails = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for the zero-based index, +1 for the consumed heading row.

            $data = [
                'name' => trim((string) ($row['nama'] ?? '')),
                'email' => Str::lower(trim((string) ($row['email'] ?? ''))),
                'password' => trim((string) ($row['password'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
                'category_aplikasi' => trim((string) ($row['category_aplikasi'] ?? '')),
                'status' => Str::lower(trim((string) ($row['status'] ?? ''))) ?: 'active',
            ];

            // Fully blank row (trailing empty line in the sheet, etc.) —
            // skip silently, it's not something to report as an error.
            if ($data['name'] === '' && $data['email'] === '') {
                continue;
            }

            $messages = [];

            if ($data['name'] === '') {
                $messages[] = 'Nama wajib diisi.';
            }

            if ($data['email'] === '') {
                $messages[] = 'Email wajib diisi.';
            } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $messages[] = 'Format email tidak valid.';
            } elseif (isset($seenEmails[$data['email']])) {
                $messages[] = "Email ini sudah dipakai di baris {$seenEmails[$data['email']]} pada file yang sama.";
            } elseif (User::where('email', $data['email'])->exists()) {
                $messages[] = 'Email ini sudah terdaftar.';
            }

            if ($data['password'] !== '' && strlen($data['password']) < 8) {
                $messages[] = 'Password minimal 8 karakter (atau kosongkan agar dibuat otomatis).';
            }

            $role = $data['role'] !== '' ? $rolesByName->get(Str::lower($data['role'])) : null;

            if ($data['role'] === '') {
                $messages[] = 'Role wajib diisi.';
            } elseif (! $role) {
                $messages[] = "Role \"{$data['role']}\" tidak ditemukan di company ini.";
            }

            $categoryNames = collect(explode(',', $data['category_aplikasi']))
                ->map(fn ($name) => trim($name))
                ->filter()
                ->values();

            $categoryIds = [];

            if ($categoryNames->isEmpty()) {
                $messages[] = 'Category Aplikasi wajib diisi (pisahkan dengan koma jika lebih dari satu).';
            } else {
                foreach ($categoryNames as $categoryName) {
                    $category = $categoriesByName->get(Str::lower($categoryName));

                    if (! $category) {
                        $messages[] = "Category Aplikasi \"{$categoryName}\" tidak valid atau tidak aktif untuk company ini.";

                        continue;
                    }

                    $categoryIds[$category->id] = true;
                }
            }

            if (! in_array($data['status'], ['active', 'inactive'], true)) {
                $messages[] = 'Status harus "active" atau "inactive".';
            }

            if ($messages !== []) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'] ?: null,
                    'messages' => $messages,
                ];

                continue;
            }

            $seenEmails[$data['email']] = $rowNumber;

            $generatedPassword = $data['password'] === '' ? Str::password(16) : null;
            $plainPassword = $generatedPassword ?? $data['password'];

            try {
                DB::transaction(function () use ($data, $role, $categoryIds, $plainPassword) {
                    // password hashes automatically — User::casts() has
                    // 'password' => 'hashed', same as CompanyUserController::store().
                    $newUser = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => $plainPassword,
                        'status' => 'active',
                        'user_type' => 'USER',
                    ]);

                    // Same as CompanyUserController::store() — owner-created
                    // accounts skip email verification.
                    $newUser->email_verified_at = now();
                    $newUser->save();

                    foreach (array_keys($categoryIds) as $categoryId) {
                        CompanyToUser::create([
                            'user_id' => $newUser->id,
                            'company_id' => $this->company->id,
                            'company_role_id' => $role->id,
                            'category_application_id' => $categoryId,
                            'status' => $data['status'],
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'email' => $data['email'],
                    'messages' => ['Gagal menyimpan baris ini, silakan coba lagi.'],
                ];

                continue;
            }

            $this->created[] = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $generatedPassword,
            ];
        }
    }
}
