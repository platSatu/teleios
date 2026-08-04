<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2 of the downloadable import template (see
 * CompanyUserImportTemplateExport) — the EXACT `role` and
 * `category_aplikasi` values App\Imports\CompanyUsersImport will accept
 * for this specific company/owner right now. Roles are per-company;
 * category applications are narrowed to whatever the owner's active
 * package currently covers (see App\Http\Controllers\User\Profile\
 * Concerns\ScopesActivePackage) — both lists are built fresh by the
 * controller on every template download, so this can never go stale the
 * way a hardcoded instructions doc would.
 */
class CompanyUserImportReferenceSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @param  Collection<int,string>  $roleNames
     * @param  Collection<int,string>  $categoryNames
     */
    public function __construct(
        private Collection $roleNames,
        private Collection $categoryNames,
    ) {
    }

    public function array(): array
    {
        $roleNames = $this->roleNames->values();
        $categoryNames = $this->categoryNames->values();

        $rowCount = max($roleNames->count(), $categoryNames->count());

        $rows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $rows[] = [
                $roleNames->get($i, ''),
                $categoryNames->get($i, ''),
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Role yang valid', 'Category Aplikasi yang valid'];
    }

    public function title(): string
    {
        return 'Referensi';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
