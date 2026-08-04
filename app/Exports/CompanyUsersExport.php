<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Export" button on the Setting Users tab (dashboard/user/profile) —
 * every member of the logged-in user's company, one row per PERSON (not
 * per company_to_users row — a member can have several of those, one per
 * granted CategoryApplication, see App\Models\CompanyToUser's docblock).
 *
 * Always built from a Company the caller already scoped to
 * Company::user_id === auth id (see CompanyUserController::export()) —
 * this class never queries Company itself, so there's no way to export
 * another company's roster by passing a different id.
 */
class CompanyUsersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private Company $company)
    {
    }

    public function collection(): Collection
    {
        return $this->company->members()
            ->with(['user', 'role', 'categoryApplication'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id')
            ->values();
    }

    public function headings(): array
    {
        return ['Nama', 'Email', 'Role', 'Category Aplikasi', 'Status', 'Ditambahkan'];
    }

    /**
     * @param  Collection  $memberRows  every company_to_users row
     *                                  belonging to one member (grouped
     *                                  by user_id in collection() above).
     */
    public function map($memberRows): array
    {
        $first = $memberRows->first();

        $categories = $memberRows
            ->pluck('categoryApplication.name')
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            $first->user->name ?? '-',
            $first->user->email ?? '-',
            $first->role->name ?? '-',
            $categories !== '' ? $categories : 'Semua Akses',
            ucfirst($first->status),
            optional($first->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
