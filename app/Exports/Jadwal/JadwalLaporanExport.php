<?php

namespace App\Exports\Jadwal;

use App\Models\JadwalKelas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Export ke Excel" untuk Laporan Jadwal (Jadwal v2, CLAUDE.md item
 * #15 spec poin 12 & 13) -- SATU file, 2 sheet, untuk rentang tanggal
 * bebas (satu hari, satu bulan, atau rentang lain) yang dipilih admin
 * di App\Http\Controllers\Jadwal\JadwalLaporanController::export():
 *   1. "Detail Sesi" -- daftar tiap sesi di rentang itu (dulu "Laporan
 *      Harian" waktu masih menu terpisah).
 *   2. "Rekap" -- ringkasan (murid aktif/baru, reschedule, total fee)
 *      + rincian per pengajar digabung dalam SATU sheet yang sama
 *      (dulu 2 sheet terpisah di "Laporan Bulanan" -- digabung supaya
 *      total export tetap 2 sheet walau sekarang cuma 1 menu Laporan).
 * Datanya dari App\Services\Jadwal\JadwalLaporanService, sumber yang
 * sama dipakai untuk render halaman, supaya angka di layar & di-export
 * selalu sama persis.
 */
class JadwalLaporanExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, JadwalKelas>  $sesi
     * @param  array  $rekap  lihat App\Services\Jadwal\JadwalLaporanService::rekap()
     */
    public function __construct(private Collection $sesi, private array $rekap, private string $rangeLabel)
    {
    }

    public function sheets(): array
    {
        return [
            new JadwalLaporanDetailSheet($this->sesi),
            new JadwalLaporanRekapSheet($this->rekap),
        ];
    }
}

class JadwalLaporanDetailSheet implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private Collection $sesi)
    {
    }

    public function collection(): Collection
    {
        return $this->sesi;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jam Mulai', 'Jam Selesai', 'Kelas', 'Kategori', 'Ruangan', 'Murid', 'Pengajar', 'Status Kehadiran'];
    }

    public function map($kelas): array
    {
        /** @var JadwalKelas $kelas */
        return [
            $kelas->start_time?->translatedFormat('d M Y'),
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
        return 'Detail Sesi';
    }
}

/**
 * Satu sheet, dua blok: ringkasan (baris 1-6) lalu tabel per pengajar
 * (mulai baris 8) -- lihat array()/styles() untuk nomor baris pastinya.
 * Sengaja TIDAK implement WithHeadings (satu sheet ini punya 2 "tabel"
 * dengan header berbeda, jadi header kedua tabel ditulis manual sebagai
 * baris array biasa, bukan lewat header sheet bawaan Excel).
 */
class JadwalLaporanRekapSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private array $rekap)
    {
    }

    public function array(): array
    {
        $rows = [
            ['RINGKASAN', ''],
            ['Jumlah Murid Aktif', $this->rekap['activeStudentCount']],
            ['Jumlah Murid Baru (periode ini)', $this->rekap['newStudentCount']],
            ['Jumlah Reschedule (periode ini)', $this->rekap['rescheduleCount']],
            ['Total Fee Company (Rp)', number_format($this->rekap['feeCompanyTotal'], 0, ',', '.')],
            ['Total Fee Pengajar (Rp)', number_format($this->rekap['feePengajarTotal'], 0, ',', '.')],
            [],
            ['PER PENGAJAR', '', '', ''],
            ['Pengajar', 'Jumlah Sesi', 'Total Jam Mengajar', 'Fee Pengajar (Rp)'],
        ];

        $perPengajar = collect($this->rekap['perPengajar']);

        foreach ($perPengajar as $row) {
            $rows[] = [
                $row['nama'],
                $row['jumlah_sesi'],
                round($row['total_menit'] / 60, 1),
                number_format($row['fee_pengajar'], 0, ',', '.'),
            ];
        }

        if ($perPengajar->isNotEmpty()) {
            $rows[] = [
                'Total',
                $perPengajar->sum('jumlah_sesi'),
                round($perPengajar->sum('total_menit') / 60, 1),
                number_format($this->rekap['feePengajarTotal'], 0, ',', '.'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $perPengajarCount = count($this->rekap['perPengajar']);

        $bold = [
            1 => ['font' => ['bold' => true]], // RINGKASAN
            8 => ['font' => ['bold' => true]], // PER PENGAJAR
            9 => ['font' => ['bold' => true]], // header tabel per pengajar
        ];

        if ($perPengajarCount > 0) {
            $bold[9 + $perPengajarCount + 1] = ['font' => ['bold' => true]]; // baris Total
        }

        return $bold;
    }

    public function title(): string
    {
        return 'Rekap';
    }
}
