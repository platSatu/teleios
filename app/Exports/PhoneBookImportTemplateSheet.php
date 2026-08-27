<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1 of the downloadable "Buku Telepon" import template (see
 * App\Exports\PhoneBookImportTemplateExport) — exactly the column
 * headers App\Imports\PhoneBookImport expects, plus one filled-in
 * example row. Deliberately holds ONLY the data columns — the
 * "Kelompok yang valid" / "Branch yang valid" reference lists used to
 * live in this same sheet (rows appended after the example row), which
 * meant re-uploading the downloaded template as-is (or any file shaped
 * like it) fed those reference rows into App\Imports\PhoneBookImport as
 * if they were real contacts. They now live on their own sheet — see
 * App\Exports\PhoneBookImportReferenceSheet — this sheet stays clean.
 */
class PhoneBookImportTemplateSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private ?string $exampleCategoryName = null)
    {
    }

    public function array(): array
    {
        return [
            ['Budi Santoso', '6281234567890', 'budi@contoh.com', $this->exampleCategoryName ?? 'Pelanggan VIP', '', 'active'],
        ];
    }

    public function headings(): array
    {
        return ['nama', 'nomor_telepon', 'email', 'kelompok', 'branch', 'status'];
    }

    public function title(): string
    {
        return 'Template';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
