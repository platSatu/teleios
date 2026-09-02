<?php

namespace App\Exports\Jadwal;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Export ke Excel" untuk Laporan Bulanan Jadwal (Jadwal v2, CLAUDE.md
 * item #15 spec poin 13) -- 2 sheet: ringkasan (murid aktif/baru,
 * jumlah reschedule, total fee company/pengajar) dan rincian per
 * pengajar (jumlah sesi, jam mengajar, fee). Datanya berasal dari
 * App\Services\Jadwal\JadwalLaporanService::bulanan(), lihat
 * App\Http\Controllers\Jadwal\JadwalLaporanController::bulananExport().
 */
class JadwalLaporanBulananExport implements WithMultipleSheets
{
    public function __construct(private array $data, private string $bulanLabel)
    {
    }

    public function sheets(): array
    {
        return [
            new JadwalLaporanBulananRingkasanSheet($this->data, $this->bulanLabel),
            new JadwalLaporanBulananPerPengajarSheet($this->data['perPengajar'], $this->bulanLabel),
        ];
    }
}

class JadwalLaporanBulananRingkasanSheet implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private array $data, private string $bulanLabel)
    {
    }

    public function array(): array
    {
        return [
            ['Jumlah Murid Aktif', $this->data['activeStudentCount']],
            ['Jumlah Murid Baru (bulan ini)', $this->data['newStudentCount']],
            ['Jumlah Reschedule (bulan ini)', $this->data['rescheduleCount']],
            ['Total Fee Company (Rp)', number_format($this->data['feeCompanyTotal'], 0, ',', '.')],
            ['Total Fee Pengajar (Rp)', number_format($this->data['feePengajarTotal'], 0, ',', '.')],
        ];
    }

    public function headings(): array
    {
        return ['Metrik', 'Nilai'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Ringkasan '.$this->bulanLabel;
    }
}

class JadwalLaporanBulananPerPengajarSheet implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private $perPengajar, private string $bulanLabel)
    {
    }

    public function array(): array
    {
        return collect($this->perPengajar)->map(fn ($row) => [
            $row['nama'],
            $row['jumlah_sesi'],
            round($row['total_menit'] / 60, 1),
            number_format($row['fee_pengajar'], 0, ',', '.'),
        ])->all();
    }

    public function headings(): array
    {
        return ['Pengajar', 'Jumlah Sesi', 'Total Jam Mengajar', 'Fee Pengajar (Rp)'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Per Pengajar '.$this->bulanLabel;
    }
}
