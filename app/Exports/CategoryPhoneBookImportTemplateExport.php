<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Download Template" inside the Kelompok import modal (Chat > Buku
 * Telepon > Kelompok) — headers + one example row, plus every branch
 * name this specific company can currently import against (so `branch`
 * doesn't require guessing exact spelling) — same "always built fresh
 * from the caller's own scope" rule as CompanyUserImportTemplateExport.
 */
class CategoryPhoneBookImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * @param  Collection<int,string>  $branchNames
     */
    public function __construct(private Collection $branchNames)
    {
    }

    public function array(): array
    {
        $rows = [
            ['Pelanggan VIP', '', 'active'],
            [], // blank separator before the branch-name reference list
            ['Branch yang valid (opsional, boleh dikosongkan):'],
        ];

        foreach ($this->branchNames as $branchName) {
            $rows[] = [$branchName];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['nama', 'branch', 'status'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
