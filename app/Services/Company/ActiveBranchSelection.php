<?php

namespace App\Services\Company;

use App\Models\BranchOffice;
use Illuminate\Support\Collection;

/**
 * Branch aktif per sesi login -- paket berlaku PER BRANCH, jadi dashboard
 * selalu berjalan "sebagai" satu branch:
 *
 *   - Member yang terkunci ke satu branch: selalu branch-nya sendiri,
 *     tidak bisa memilih (tidak ada di pemilih branch).
 *   - Owner / member tingkat company: memilih lewat pemilih branch di
 *     header (Dashboard\ActiveBranchController), disimpan di session.
 *     Pilihan yang tidak valid (branch sudah dihapus / bukan milik
 *     company ini) diabaikan dan jatuh ke branch pertama.
 *
 * Session hanya dibaca kalau request punya session (aman dipanggil dari
 * job/command -- hasilnya branch pertama).
 */
class ActiveBranchSelection
{
    public const SESSION_KEY = 'active_branch_office_id';

    /**
     * Branch yang boleh dibuka user dengan konteks ini.
     *
     * @return Collection<int, BranchOffice>
     */
    public function branchesFor(CompanyContext $context): Collection
    {
        if ($context->isLockedToBranch()) {
            return collect([$context->branchOffice])->filter()->values();
        }

        return $context->company->branchOffices()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function resolve(CompanyContext $context): ?BranchOffice
    {
        if ($context->isLockedToBranch()) {
            return $context->branchOffice;
        }

        $branches = $this->branchesFor($context);
        $selectedId = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;

        return $branches->firstWhere('id', $selectedId) ?? $branches->first();
    }

    /**
     * Simpan pilihan branch. Mengembalikan false (tidak menyimpan apa pun)
     * kalau branch itu tidak boleh dibuka user dengan konteks ini.
     */
    public function select(CompanyContext $context, string $branchOfficeId): bool
    {
        if (! $this->branchesFor($context)->contains('id', $branchOfficeId)) {
            return false;
        }

        request()->session()->put(self::SESSION_KEY, $branchOfficeId);

        return true;
    }
}
