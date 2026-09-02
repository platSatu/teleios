<?php

namespace App\Exports\Jadwal;

use App\Models\JadwalKelas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Export ke Excel" untuk Laporan Harian Jadwal (Jadwal v2, CLAUDE.md
 * item #15 spec poin 12) -- dibangun dari koleksi App\Models\JadwalKelas
 * yang query-nya SUDAH di-scope company/branch/tanggal oleh
 * App\Services\Jadwal\JadwalLaporanService::harian(), lihat
 * App\Http\Controllers\Jadwal\JadwalLaporanController::harianExport().
 */
class JadwalLaporanHarianExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private Collection $sesi, private string $tanggalLabel)
    {
    }

    public function collection(): Collection
    {
        return $this->sesi;
    }

    public function headings(): array
    {
        return ['Jam Mulai', 'Jam Selesai', 'Kelas', 'Kategori', 'Ruangan', 'Murid', 'Pengajar', 'Status Kehadiran'];
    }

    public function map($kelas): array
    {
        /** @var JadwalKelas $kelas */
        return [
            $kelas->start_time?->format('H:i'),
            $kelas->end_time?->format('H:i'),
            $kelas->mataPelajaran?->name ?? '-',
            $kelas->kategori?->name ?? '-',
            $kelas->ruangan?->name ?? '-',
            $kelas->student?->name ?? '-',
            $kelas->pengajar?->name ?? '-',
            match ($kelas->attendance_status) {
                'hadir' => 'Hadir',
                'tidak_hadir' => 'Tidak Hadir (hangus)',
                'izin' => 'Izin/Sakit',
                default => 'Belum Diabsen',
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Laporan Harian '.$this->tanggalLabel;
    }
}
