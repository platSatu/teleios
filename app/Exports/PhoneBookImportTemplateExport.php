<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Download Template" inside the Buku Telepon import modal — headers +
 * one example row, plus every Kelompok/branch name this specific
 * company can currently import against. See
 * App\Exports\CategoryPhoneBookImportTemplateExport for the same
 * pattern applied to Kelompok itself.
 */
class PhoneBookImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * @param  Collection<int,string>  $categoryNames
     * @param  Collection<int,string>  $branchNames
     */
    public function __construct(
        private Collection $categoryNames,
        private Collection $branchNames,
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['Budi Santoso', '6281234567890', 'budi@contoh.com', $this->categoryNames->first() ?? 'Pelanggan VIP', '', 'active'],
            [],
            ['Kelompok yang valid:'],
        ];

        foreach ($this->categoryNames as $name) {
            $rows[] = [$name];
        }

        $rows[] = [];
        $rows[] = ['Branch yang valid (opsional, boleh dikosongkan):'];

        foreach ($this->branchNames as $name) {
            $rows[] = [$name];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['nama', 'nomor_telepon', 'email', 'kelompok', 'branch', 'status'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
