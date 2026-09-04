{{--
    Update 4 September 2026 (bagian dari redesign grid per Ruangan,
    lihat docblock JadwalKelasController::index()): sel-sel <td> untuk
    satu baris App\Models\JadwalKelas -- di-extract dari tabel flat
    index.blade.php yang lama supaya markup PERSIS SAMA dipakai di DUA
    tempat: baris grid (slot waktu yang kebetulan ada sesinya) dan
    daftar "Sesi Tanpa Jam / Di Luar Jam Operasional" di atas tiap tab
    (lihat JadwalKelasController::buildRuanganGrid()'s `unmatched`).
    Kolom Ruangan SENGAJA tidak ada di sini (beda dari tabel lama) --
    Ruangan sekarang jadi tab pembungkusnya, bukan kolom lagi.

    SENGAJA TIDAK membungkus <tr> sendiri (beda dari sebelumnya) --
    pemanggil (index.blade.php) yang membungkus <tr>, supaya bisa
    menambahkan kolom "No" di depan tanpa duplikasi markup di dua
    tempat.

    Variabel yang diharapkan:
    - $kelas: satu instance App\Models\JadwalKelas (dengan relasi
      pengajar/student/mataPelajaran/kategori/sesiPengganti/
      penggantiDariSesi sudah di-eager-load dari index()).
--}}
    <td class="text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
    <td class="text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
    <td class="text-nowrap">{{ $kelas->kategori->name ?? '-' }}</td>
    <td class="text-nowrap">{{ $kelas->student->name ?? 'Slot Kosong' }}</td>
    <td class="text-nowrap">{{ $kelas->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
    <td class="text-nowrap">{{ $kelas->end_time?->format('d/m/Y H:i') ?? '-' }}</td>
    <td>
        {{--
            Update 4 September 2026 (permintaan user: fungsi Cetak) --
            form kehadiran ini interaktif (dropdown+input+tombol), tidak
            berguna di kertas -- disembunyikan saat cetak (`d-print-none`)
            dan diganti teks polos di bawahnya (`d-print-block`, cuma
            muncul saat cetak) supaya status kehadiran tetap kebaca di
            hasil cetak.
        --}}
        <form action="{{ route('jadwal.kelas.attendance.update', $kelas->id) }}" method="POST" class="d-flex flex-nowrap align-items-center gap-2 mb-1 d-print-none">
            @csrf
            @method('PATCH')
            <select name="attendance_status" class="form-select form-select-sm" style="width: 150px; flex: 0 0 auto;" onchange="this.form.submit()">
                <option value="" @selected(!$kelas->attendance_status)>Belum Diabsen</option>
                <option value="hadir" @selected($kelas->attendance_status === 'hadir')>Hadir</option>
                <option value="tidak_hadir" @selected($kelas->attendance_status === 'tidak_hadir')>Tidak Hadir (hangus)</option>
                <option value="izin" @selected($kelas->attendance_status === 'izin')>Izin/Sakit (dapat pengganti)</option>
            </select>
            <div class="input-group input-group-sm" style="width: 160px; flex: 0 0 auto;">
                <input type="text" name="attendance_notes" value="{{ $kelas->attendance_notes }}" class="form-control form-control-sm" placeholder="Keterangan">
                <button type="submit" class="btn btn-outline-secondary" title="Simpan keterangan"><i class="ri-save-line"></i></button>
            </div>
        </form>
        <div class="d-none d-print-block">
            {{ match($kelas->attendance_status) {
                'hadir' => 'Hadir',
                'tidak_hadir' => 'Tidak Hadir (hangus)',
                'izin' => 'Izin/Sakit',
                default => 'Belum Diabsen',
            } }}
            @if($kelas->attendance_notes)
                -- {{ $kelas->attendance_notes }}
            @endif
        </div>
        @if($kelas->attendance_status === 'izin')
            @if($kelas->sesiPengganti)
                <span class="badge bg-info-subtle text-info">
                    <i class="ri-repeat-line"></i> Pengganti: {{ $kelas->sesiPengganti->start_time?->format('d/m/Y H:i') }}
                </span>
            @else
                <a href="{{ route('jadwal.kelas.create', ['pengganti_dari_sesi_id' => $kelas->id]) }}" class="btn btn-xs btn-outline-info" style="font-size: .75rem; padding: .1rem .4rem;">
                    <i class="ri-add-line"></i> Buat Sesi Pengganti
                </a>
            @endif
        @endif
        @if($kelas->penggantiDariSesi)
            <span class="badge bg-secondary-subtle text-secondary d-block mt-1" style="width: fit-content;">
                Pengganti dari {{ $kelas->penggantiDariSesi->start_time?->format('d/m/Y H:i') }}
            </span>
        @endif
    </td>
    <td>
        <span class="badge {{ $kelas->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kelas->status }}</span>
    </td>
    <td class="text-end text-nowrap d-print-none">
        @if($kelas->start_time)
            <form action="{{ route('jadwal.kelas.pengajar-reminder.send', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Kirim rekap jadwal ke pengajar {{ $kelas->pengajar->name ?? '' }} untuk tanggal {{ $kelas->start_time->format('d/m/Y') }}?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-light" title="Kirim reminder ke pengajar">
                    <i class="ri-notification-3-line"></i>
                </button>
            </form>
        @endif
        {{--
            Update 4 September 2026 (permintaan user: "perbaiki fungsi
            edit nya ya dari jadwal kelas, itu masih yang lama, di buat
            modern popup saja"). SEBELUMNYA tombol ini `<a>` biasa yang
            pindah ke halaman penuh jadwal.kelas.edit (masih ada, jadi
            fallback -- lihat docblock JadwalKelasController::edit()).
            SEKARANG tombol ini `<button>` yang membuka #editKelasModal
            (SATU modal dipakai ULANG untuk semua baris, lihat
            index.blade.php -- bukan satu modal per baris supaya HTML
            halaman tidak membengkak kalau baris sesinya banyak) --
            data-kelas berisi nilai field kelas ini APA ADANYA, dibaca
            script index.blade.php saat tombol diklik untuk mengisi
            form popup (Mata Pelajaran -> Kategori -> Pengajar -> tab
            Jam Pengajar, lihat docblock JadwalKelasController::
            editModalData()).
        --}}
        <button type="button" class="btn btn-sm btn-light js-edit-kelas-btn" title="Edit"
            data-kelas="{{ json_encode([
                'id' => $kelas->id,
                'update_url' => route('jadwal.kelas.update', $kelas->id),
                'branch_office_id' => $kelas->branch_office_id,
                'jadwal_mata_pelajaran_id' => $kelas->jadwal_mata_pelajaran_id,
                'jadwal_kategori_id' => $kelas->jadwal_kategori_id,
                'pengajar_id' => $kelas->pengajar_id,
                'student_id' => $kelas->student_id,
                'jadwal_ruangan_id' => $kelas->jadwal_ruangan_id,
                'date' => $kelas->start_time?->toDateString(),
                'jam_mulai' => $kelas->start_time?->format('H:i'),
                'jam_selesai' => $kelas->end_time?->format('H:i'),
                'description' => $kelas->description,
                'status' => $kelas->status,
            ]) }}">
            <i class="ri-edit-line"></i>
        </button>
        <form action="{{ route('jadwal.kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
        </form>
    </td>
