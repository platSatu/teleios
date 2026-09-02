<?php

namespace App\Services\Jadwal;

use App\Models\JadwalRutin;

/**
 * Validasi Jadwal Rutin (Jadwal v2, CLAUDE.md item #15, spec poin 5) --
 * dipanggil dari App\Http\Controllers\Jadwal\JadwalRutinController SAAT
 * baris Jadwal Rutin dibuat/disimpan (bukan nanti saat generate sesi).
 * Per keputusan diskusi dengan user: "harusnya tidak ada yang bentrok
 * karena kan 1 kelas 1 ruangan 1 guru jadi sifatnya private" -- jadi
 * cukup 1 pengecekan overlap waktu di titik input ini, TIDAK perlu
 * resolver bentrok yang rumit saat generate sesi bulanan.
 *
 * Dua baris Jadwal Rutin dianggap BENTROK kalau: hari sama, rentang
 * tanggal efektif (efektif_mulai..efektif_selesai, null = tak terbatas)
 * beririsan, DAN rentang jam (jam_mulai..jam_mulai+durasi) beririsan --
 * dicek terpisah untuk pengajar yang sama dan (kalau diisi) ruangan
 * yang sama.
 */
class JadwalRutinConflictService
{
    /**
     * @return array<int, string> pesan error (kosong = tidak ada bentrok)
     */
    public function check(
        string $companyId,
        int $hari,
        string $jamMulai,
        string $jamSelesai,
        string $efektifMulai,
        ?string $efektifSelesai,
        string $pengajarId,
        ?string $jadwalRuanganId,
        ?string $ignoreId = null,
    ): array {
        $errors = [];

        $pengajarConflict = $this->findConflict(
            $companyId, $hari, $jamMulai, $jamSelesai, $efektifMulai, $efektifSelesai, $ignoreId,
            pengajarId: $pengajarId,
        );

        if ($pengajarConflict) {
            $errors[] = sprintf(
                'Pengajar sudah punya Jadwal Rutin lain di hari %s jam %s-%s (murid: %s). Pilih jam/hari lain, atau ubah/nonaktifkan jadwal yang bentrok itu dulu.',
                JadwalRutin::HARI_LABELS[$hari] ?? $hari,
                substr($pengajarConflict->jam_mulai, 0, 5),
                $pengajarConflict->jamSelesai(),
                $pengajarConflict->student?->name ?? '-',
            );
        }

        if ($jadwalRuanganId) {
            $ruanganConflict = $this->findConflict(
                $companyId, $hari, $jamMulai, $jamSelesai, $efektifMulai, $efektifSelesai, $ignoreId,
                jadwalRuanganId: $jadwalRuanganId,
            );

            if ($ruanganConflict) {
                $errors[] = sprintf(
                    'Ruangan sudah dipakai Jadwal Rutin lain di hari %s jam %s-%s (murid: %s). Pilih ruangan/jam lain.',
                    JadwalRutin::HARI_LABELS[$hari] ?? $hari,
                    substr($ruanganConflict->jam_mulai, 0, 5),
                    $ruanganConflict->jamSelesai(),
                    $ruanganConflict->student?->name ?? '-',
                );
            }
        }

        return $errors;
    }

    /**
     * Cari satu baris Jadwal Rutin aktif yang bentrok, untuk SATU sisi
     * saja (pengajar ATAU ruangan -- persis salah satu argumen ini yang
     * diisi tiap panggilan, lihat check() di atas).
     */
    private function findConflict(
        string $companyId,
        int $hari,
        string $jamMulai,
        string $jamSelesai,
        string $efektifMulai,
        ?string $efektifSelesai,
        ?string $ignoreId,
        ?string $pengajarId = null,
        ?string $jadwalRuanganId = null,
    ): ?JadwalRutin {
        $query = JadwalRutin::where('company_id', $companyId)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->where('hari', $hari)
            ->with('student:id,name');

        if ($pengajarId) {
            $query->where('pengajar_id', $pengajarId);
        } else {
            $query->where('jadwal_ruangan_id', $jadwalRuanganId);
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        // Irisan rentang tanggal efektif: candidate.efektif_selesai (atau
        // tak terbatas) >= this.efektif_mulai, DAN (kalau this.efektif_selesai
        // diisi) candidate.efektif_mulai <= this.efektif_selesai.
        $query->where(function ($q) use ($efektifMulai) {
            $q->whereNull('efektif_selesai')->orWhere('efektif_selesai', '>=', $efektifMulai);
        });

        if ($efektifSelesai) {
            $query->where('efektif_mulai', '<=', $efektifSelesai);
        }

        foreach ($query->get() as $candidate) {
            $candidateStart = substr($candidate->jam_mulai, 0, 5);
            $candidateEnd = $candidate->jamSelesai();

            // Overlap waktu: start < candidateEnd DAN end > candidateStart.
            if ($jamMulai < $candidateEnd && $jamSelesai > $candidateStart) {
                return $candidate;
            }
        }

        return null;
    }
}
