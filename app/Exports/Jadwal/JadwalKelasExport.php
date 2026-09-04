<?php

namespace App\Exports\Jadwal;

use App\Models\JadwalKelas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Update 4 September 2026 (permintaan user: "tambahkan fungsi export
 * to excel sesuai dengan filter" -- untuk halaman "Jadwal Kelas",
 * App\Http\Controllers\Jadwal\JadwalKelasController::export()). SATU
 * file, SATU sheet per tab Ruangan yang tampil di layar (termasuk
 * "Tanpa Ruangan" kalau ada) -- pola SAMA dengan App\Exports\Jadwal\
 * JadwalLaporanExport (WithMultipleSheets, satu class per sheet), tapi
 * beda dari itu: Laporan datanya rentang tanggal bebas + rekap
 * ringkasan, export ini datanya SATU tanggal (persis filter aktif di
 * layar) tanpa rekap -- murni daftar sesi per Ruangan.
 *
 * Datanya (sesi per Ruangan, sudah difilter Tanggal/Pengajar/Mata
 * Pelajaran/Branch) berasal dari App\Http\Controllers\Jadwal\
 * JadwalKelasController::resolveFilteredSesi() -- SATU SUMBER dipakai
 * index() (tampilan grid) juga, supaya export ini DIJAMIN selalu
 * "sesuai dengan filter" yang sedang aktif di layar, tidak mungkin
 * meleset.
 */
class JadwalKelasExport implements WithMultipleSheets
{
    /**
     * @param  list<array{name: string, sesi: Collection<int, JadwalKelas>}>  $sheets
     */
    public function __construct(
        private array $sheets,
        private Carbon $date,
        private ?string $branchName,
        private ?string $pengajarName,
        private ?string $mataPelajaranName,
    ) {
    }

    public function sheets(): array
    {
        return collect($this->sheets)
            ->map(fn (array $s) => new JadwalKelasRuanganSheet(
                $s['sesi'], $s['name'], $this->date, $this->branchName, $this->pengajarName, $this->mataPelajaranName
            ))
            ->all();
    }
}

/**
 * Satu sheet = satu Ruangan (atau "Tanpa Ruangan"). Beberapa baris info
 * (Ruangan/Tanggal/Branch/filter Pengajar & Mata Pelajaran kalau ada)
 * di atas, baru tabel sesi -- pola header yang sama semangatnya dengan
 * contoh gambar mockup Excel yang dikirim user waktu mendesain grid
 * Jadwal Kelas ("Hari Senin 07 September 2026 | Ruangan A").
 */
class JadwalKelasRuanganSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private Collection $sesi,
        private string $ruanganName,
        private Carbon $date,
        private ?string $branchName,
        private ?string $pengajarName,
        private ?string $mataPelajaranName,
    ) {
    }

    /**
     * Baris info di atas tabel -- dihitung lewat method terpisah (bukan
     * ditulis dua kali di array()/styles()) supaya jumlah barisnya
     * (dipakai styles() untuk tahu baris mana yang di-bold) SELALU
     * konsisten dengan yang benar-benar ditulis array().
     *
     * @return list<array{0: string, 1?: string}>
     */
    private function infoRows(): array
    {
        $rows = [
            ['Ruangan', $this->ruanganName],
            ['Tanggal', $this->date->translatedFormat('l, d F Y')],
        ];

        if ($this->branchName) {
            $rows[] = ['Branch', $this->branchName];
        }

        if ($this->pengajarName) {
            $rows[] = ['Filter Pengajar', $this->pengajarName];
        }

        if ($this->mataPelajaranName) {
            $rows[] = ['Filter Mata Pelajaran / Bidang', $this->mataPelajaranName];
        }

        return $rows;
    }

    public function array(): array
    {
        $rows = $this->infoRows();
        $rows[] = [];
        $rows[] = ['No', 'Pengajar', 'Bidang', 'Kategori', 'Murid', 'Mulai', 'Selesai', 'Status Kehadiran', 'Status'];

        $no = 1;

        foreach ($this->sesi as $kelas) {
            /** @var JadwalKelas $kelas */
            $rows[] = [
                $no++,
                $kelas->pengajar?->name ?? '-',
                $kelas->mataPelajaran?->name ?? '-',
                $kelas->kategori?->name ?? '-',
                $kelas->student?->name ?? 'Slot Kosong',
                $kelas->start_time?->format('d/m/Y H:i') ?? '-',
                $kelas->end_time?->format('d/m/Y H:i') ?? '-',
                match ($kelas->attendance_status) {
                    'hadir' => 'Hadir',
                    'tidak_hadir' => 'Tidak Hadir (hangus)',
                    'izin' => 'Izin/Sakit',
                    default => 'Belum Diabsen',
                },
                $kelas->status === JadwalKelas::STATUS_ACTIVE ? 'Active' : 'Inactive',
            ];
        }

        if ($this->sesi->isEmpty()) {
            $rows[] = ['Tidak ada Jadwal Kelas untuk Ruangan ini pada tanggal & filter yang dipilih.'];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Baris header tabel = jumlah baris info + 1 baris kosong + 1.
        $headerRow = count($this->infoRows()) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $headerRow => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Nama sheet Excel dibatasi 31 karakter & tidak boleh mengandung
     * : \ / ? * [ ] -- Ruangan nama bebas isian admin, jadi disaring
     * dulu supaya tidak error saat file di-generate kalau kebetulan
     * ada karakter itu di nama Ruangan.
     */
    public function title(): string
    {
        $title = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $this->ruanganName) ?? $this->ruanganName;
        $title = trim($title) ?: 'Ruangan';

        return mb_substr($title, 0, 31);
    }
}
