<?php

namespace App\Exports;

use App\Models\WaPhoneBook;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Export" button on Chat > Buku Telepon — every entry the caller can
 * see, as an .xlsx download. Always built from a query the controller
 * already scoped to the caller's own company (and branch, if locked) —
 * see Chat\PhoneBookController::export() — this class never re-derives
 * that scope itself.
 */
class PhoneBooksExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Nama', 'Nomor Telepon', 'Email', 'Kelompok', 'Branch', 'Status', 'Blacklist'];
    }

    public function map($phoneBook): array
    {
        /** @var WaPhoneBook $phoneBook */
        return [
            $phoneBook->name,
            $phoneBook->phone,
            $phoneBook->email ?? '-',
            $phoneBook->category->name ?? '-',
            $phoneBook->branchOffice->name ?? '-',
            ucfirst($phoneBook->status),
            $phoneBook->is_blacklisted ? 'Ya' : 'Tidak',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
