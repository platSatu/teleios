<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill "Grade default" untuk SEMUA Kategori yang sudah ada SEBELUM
 * fitur Grade ini dibuat (permintaan user 8 September 2026, dikonfirmasi
 * eksplisit lewat pertanyaan: "Setuju dibuatkan 1 Grade default otomatis
 * per Kategori lama... supaya kelas yang sedang rutin jalan sekarang
 * tidak berhenti begitu fitur Grade aktif?" -> Setuju).
 *
 * TIDAK menebak/memisah nama Kategori (mis. "Piano Classic Level 1")
 * jadi nama+grade terpisah -- user eksplisit menolak itu. Ini MURNI
 * membungkus data yang SUDAH ADA (harga_bulanan, persentase_company,
 * persentase_pengajar, penugasan Pengajar + jam ketersediaannya) apa
 * adanya ke SATU baris Grade baru per Kategori, supaya:
 *  1. App\Services\Jadwal\JadwalRutinSesiGenerator tetap bisa menemukan
 *     harga (sekarang dibaca dari Grade, bukan Kategori langsung) untuk
 *     App\Models\JadwalRutin yang SUDAH aktif jalan sebelum fitur ini,
 *  2. Penugasan Pengajar (App\Models\JadwalPengajarKategori LAMA) tetap
 *     "ada" dalam bentuk barunya (App\Models\JadwalPengajarGrade), tidak
 *     hilang begitu menu Pengajar mulai baca tabel baru.
 *
 * SEPENUHNYA idempotent -- aman dijalankan ulang (mis. migrate sempat
 * terhenti di tengah jalan): kalau satu Kategori SUDAH punya baris Grade
 * apa pun (baik dari backfill sebelumnya MAUPUN Grade asli yang sudah
 * sempat dibuat admin lewat form sebelum migration ini sempat jalan),
 * Kategori itu DILEWATI SELURUHNYA -- tidak dibuatkan Grade default
 * kedua, tidak menyentuh penugasan Pengajar/Jadwal Rutin/Jadwal Kelas
 * yang terkait, supaya tidak ada duplikat kalau di-rerun.
 *
 * Pakai query builder (DB::table) langsung, bukan Eloquent model --
 * data migration murni copy nilai kolom, tidak perlu model event/cast
 * apa pun, dan lebih aman dari perubahan model di masa depan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $kategoris = DB::table('jadwal_kategori')->get();

        foreach ($kategoris as $kategori) {
            $sudahPunyaGrade = DB::table('jadwal_grade')
                ->where('jadwal_kategori_id', $kategori->id)
                ->exists();

            if ($sudahPunyaGrade) {
                continue;
            }

            $gradeId = (string) Str::uuid();

            DB::table('jadwal_grade')->insert([
                'id' => $gradeId,
                'company_id' => $kategori->company_id,
                'jadwal_kategori_id' => $kategori->id,
                'name' => $kategori->name,
                'harga_bulanan' => $kategori->harga_bulanan,
                'persentase_company' => $kategori->persentase_company,
                'persentase_pengajar' => $kategori->persentase_pengajar,
                'status' => $kategori->status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Pindahkan penugasan Pengajar lama (JadwalPengajarKategori)
            // ke bentuk barunya (JadwalPengajarGrade) -- baris LAMA di
            // jadwal_pengajar_kategori SENGAJA tidak disentuh/dihapus
            // (lihat docblock create_jadwal_pengajar_grade_table.php).
            $pengajarKategoris = DB::table('jadwal_pengajar_kategori')
                ->where('jadwal_kategori_id', $kategori->id)
                ->get();

            foreach ($pengajarKategoris as $pk) {
                $pengajarGradeId = (string) Str::uuid();

                DB::table('jadwal_pengajar_grade')->insert([
                    'id' => $pengajarGradeId,
                    'company_id' => $pk->company_id,
                    'jadwal_grade_id' => $gradeId,
                    'pengajar_id' => $pk->pengajar_id,
                    'status' => $pk->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $slots = DB::table('jadwal_pengajar_kategori_jadwal')
                    ->where('jadwal_pengajar_kategori_id', $pk->id)
                    ->get();

                foreach ($slots as $slot) {
                    DB::table('jadwal_pengajar_grade_jadwal')->insert([
                        'id' => (string) Str::uuid(),
                        'jadwal_pengajar_grade_id' => $pengajarGradeId,
                        'hari' => $slot->hari,
                        'jam_mulai' => $slot->jam_mulai,
                        'jam_selesai' => $slot->jam_selesai,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Backfill jadwal_grade_id untuk Jadwal Rutin & Jadwal Kelas
            // yang SUDAH ADA di bawah Kategori ini -- supaya generator
            // sesi bulanan & histori snapshot tetap punya acuan Grade,
            // bukan cuma Kategori. Hanya baris yang jadwal_grade_id-nya
            // masih kosong (aman di-rerun, tidak menimpa yang sudah
            // sempat diisi manual).
            DB::table('jadwal_rutin')
                ->where('jadwal_kategori_id', $kategori->id)
                ->whereNull('jadwal_grade_id')
                ->update(['jadwal_grade_id' => $gradeId]);

            DB::table('jadwal_kelas')
                ->where('jadwal_kategori_id', $kategori->id)
                ->whereNull('jadwal_grade_id')
                ->update(['jadwal_grade_id' => $gradeId]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak ada rollback data (down() no-op) -- membongkar
        // balik backfill ini berisiko menghapus Grade yang TERLANJUR
        // dipakai admin secara nyata (mis. sempat mengedit nama/harga
        // Grade default itu) setelah migration ini jalan. Struktur
        // tabelnya sendiri (down() migration lain di batch yang sama)
        // tetap bisa di-drop seperti biasa kalau perlu rollback penuh.
    }
};
