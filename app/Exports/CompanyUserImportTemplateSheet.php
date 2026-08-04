<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1 of the downloadable import template (see
 * CompanyUserImportTemplateExport) — exactly the column headers
 * App\Imports\CompanyUsersImport expects, plus one filled-in example row
 * so the shape (and the comma-separated multi-category convention in
 * `category_aplikasi`) is obvious without reading docs.
 *
 * `password` is deliberately shown blank in the example — leaving it
 * blank in a real import is valid too; CompanyUsersImport generates a
 * random one when that happens rather than accepting a weak default.
 */
class CompanyUserImportTemplateSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['Budi Santoso', 'budi@contoh.com', '', 'Staff', 'Chat, WhatsApp', 'active'],
        ];
    }

    public function headings(): array
    {
        return ['nama', 'email', 'password', 'role', 'category_aplikasi', 'status'];
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
