<?php

namespace App\Exports\Jadwal;

use App\Services\Jadwal\JadwalLaporanService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Laporan Jadwal (redesign 8 Oktober 2026) -- 3 sheet yang isinya
 * sama persis dengan halaman: Ringkasan (4 box), Student, Pengajar.
 * Data dari App\Services\Jadwal\JadwalLaporanService::build().
 */
class JadwalLaporanExport implements WithMultipleSheets
{
    public function __construct(private array $laporan, private string $rangeLabel)
    {
    }

    public function sheets(): array
    {
        $l = $this->laporan;

        $pengajarRows = $l['pengajar']->map(fn ($r) => [
            $r['pengajar'], $r['ruangan'], $r['kelas'], $r['kategori'], $r['grade'],
            $r['jumlah_sesi'], JadwalLaporanService::formatJam($r['total_menit']), $r['total_menit'], $r['fee_pengajar'],
        ])->all();

        if ($pengajarRows) {
            $pengajarRows[] = [
                'Total', '', '', '', '',
                $l['pengajar']->sum('jumlah_sesi'),
                JadwalLaporanService::formatJam($l['pengajar']->sum('total_menit')),
                $l['pengajar']->sum('total_menit'),
                round($l['pengajar']->sum('fee_pengajar'), 2),
            ];
        }

        return [
            new JadwalLaporanSheet('Ringkasan', ['Periode', $this->rangeLabel], [
                ['Total Murid (aktif)', $l['totalMurid']],
                ['Murid Baru (periode ini)', $l['muridBaru']],
                ['Mata Pelajaran / Bidang', $l['mataPelajaranCount']],
                ['Kategori', $l['kategoriCount']],
                ['Grade', $l['gradeCount']],
                ['Omset / Bulan (Rp) -- murid aktif × harga Grade', $l['omset']],
            ]),
            new JadwalLaporanSheet('Student',
                ['Murid', 'Pengajar', 'Ruangan', 'Kelas', 'Kategori', 'Grade', 'Hadir', 'Tidak Hadir', 'Izin', 'Belum Diabsen'],
                $l['students']->map(fn ($r) => [
                    $r['murid'], $r['pengajar'], $r['ruangan'], $r['kelas'], $r['kategori'], $r['grade'],
                    $r['hadir'], $r['tidak_hadir'], $r['izin'], $r['belum'],
                ])->all()),
            new JadwalLaporanSheet('Pengajar',
                ['Pengajar', 'Ruangan', 'Kelas', 'Kategori', 'Grade', 'Jumlah Sesi', 'Total Jam Mengajar', 'Total Menit', 'Fee Pengajar (Rp)'],
                $pengajarRows,
                boldLastRow: (bool) $pengajarRows),
        ];
    }
}

class JadwalLaporanSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private string $title, private array $heading, private array $rows, private bool $boldLastRow = false)
    {
    }

    public function array(): array
    {
        return array_merge([$this->heading], $this->rows);
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [1 => ['font' => ['bold' => true]]];

        if ($this->boldLastRow) {
            $styles[count($this->rows) + 1] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function title(): string
    {
        return $this->title;
    }
}
