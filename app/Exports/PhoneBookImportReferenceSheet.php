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
 * Sheet 2 of the downloadable "Buku Telepon" import template (see
 * App\Exports\PhoneBookImportTemplateExport) — the EXACT `kelompok` and
 * `branch` values App\Imports\PhoneBookImport will accept for this
 * specific company right now, built fresh by the controller on every
 * template download (same "always built fresh from the caller's own
 * scope" rule as App\Exports\CompanyUserImportReferenceSheet). Used to
 * live as extra rows tacked onto the bottom of the data sheet itself —
 * moved to its own sheet so the data sheet stays clean (see
 * App\Exports\PhoneBookImportTemplateSheet's docblock for why that
 * mattered).
 *
 * Deliberately safe if someone re-uploads this whole template file back
 * as an import: this sheet's own heading row is 'Kelompok yang valid' /
 * 'Branch yang valid', not 'nama'/'nomor_telepon' — so under
 * App\Imports\PhoneBookImport's WithHeadingRow, every data row on THIS
 * sheet has neither a `nama` nor a `nomor_telepon`/`phone` key at all,
 * which is exactly the "no real data in this row" condition
 * PhoneBookImport::collection() filters out before anything is even
 * validated. It's silently skipped as an empty sheet, never misread as
 * a batch of broken contact rows.
 */
class PhoneBookImportReferenceSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
        $categoryNames = $this->categoryNames->values();
        $branchNames = $this->branchNames->values();

        $rowCount = max($categoryNames->count(), $branchNames->count());

        $rows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $rows[] = [
                $categoryNames->get($i, ''),
                $branchNames->get($i, ''),
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Kelompok yang valid', 'Branch yang valid (opsional, boleh dikosongkan)'];
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
