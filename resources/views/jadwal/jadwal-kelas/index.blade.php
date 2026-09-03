@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @php
            // Dipakai berkali-kali di bawah (tab, tombol Tambah, link
            // prev/next hari) supaya konteks (ruangan_id + date) tidak
            // pernah hilang pindah halaman -- lihat class docblock
            // App\Http\Controllers\Jadwal\JadwalKelasController.
            $dateStr = $date->toDateString();
        @endphp

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Jadwal Kelas</h4>
                        <p class="text-muted mb-0">Grid per Ruangan &amp; hari -- klik "+" di slot kosong untuk menambah, klik tab untuk pindah Ruangan.</p>
                    </div>
                    <a href="{{ route('jadwal.kelas.create', ['ruangan_id' => $activeRuanganId, 'date' => $dateStr]) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Jadwal Kelas
                    </a>
                </div>

                {{-- Tab per Ruangan + 1 tab "Tanpa Ruangan" (sesi yang
                belum diisi ruangannya -- lihat docblock App\Models\
                JadwalKelas soal ruangan opsional). Server-rendered link
                (bukan JS toggle) supaya tiap tab murni full page load
                dgn query string berbeda, sama pola filter Jadwal lain
                di app ini. --}}
                <ul class="nav nav-pills mb-3 flex-wrap gap-1">
                    @forelse($ruangans as $r)
                        <li class="nav-item">
                            <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $r->id, 'date' => $dateStr]) }}"
                                class="nav-link {{ $activeRuanganId === $r->id ? 'active' : '' }}">
                                <i class="ri-door-open-line"></i> {{ $r->name }}
                            </a>
                        </li>
                    @empty
                        <li class="nav-item">
                            <span class="text-muted small align-self-center">Belum ada Ruangan terdaftar -- <a href="{{ route('jadwal.ruangan.index') }}">tambah dulu di sini</a>.</span>
                        </li>
                    @endforelse
                    <li class="nav-item">
                        <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => 'none', 'date' => $dateStr]) }}"
                            class="nav-link {{ $activeRuanganId === 'none' ? 'active' : '' }}">
                            <i class="ri-question-line"></i> Tanpa Ruangan
                        </a>
                    </li>
                </ul>

                @if($activeRuangan)
                    {{-- Navigasi tanggal -- kemarin/besok geser 1 hari,
                    date picker auto-submit, "Hari Ini" balik cepat. --}}
                    <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <input type="hidden" name="ruangan_id" value="{{ $activeRuanganId }}">
                        <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $activeRuanganId, 'date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-light btn-sm" title="Hari sebelumnya">
                            <i class="ri-arrow-left-s-line"></i>
                        </a>
                        <input type="date" name="date" value="{{ $dateStr }}" class="form-control form-control-sm" style="width: 160px;" onchange="this.form.submit()">
                        <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $activeRuanganId, 'date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-light btn-sm" title="Hari berikutnya">
                            <i class="ri-arrow-right-s-line"></i>
                        </a>
                        @if($dateStr !== now()->toDateString())
                            <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $activeRuanganId, 'date' => now()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">Hari Ini</a>
                        @endif
                        <span class="text-muted small ms-2">{{ $date->translatedFormat('l, d F Y') }}</span>
                    </form>

                    @if(!$branchSetting)
                        <div class="alert alert-warning">
                            <i class="ri-error-warning-line"></i> Branch untuk Ruangan "{{ $activeRuangan->name }}" belum diatur Jam Operasionalnya -- grid di bawah pakai jam default 08:00-20:00 sementara. <a href="{{ route('jadwal.branch.index') }}">Atur Jam Operasional</a> supaya grid sesuai jam buka toko sebenarnya.
                        </div>
                    @endif

                    @if($noTimeInRoom->isNotEmpty())
                        <div class="mb-3">
                            <div class="text-muted small mb-1"><i class="ri-time-line"></i> Sesi di ruangan ini yang belum punya jam (tidak terikat tanggal manapun sampai diisi):</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($noTimeInRoom as $kelas)
                                    <a href="{{ route('jadwal.kelas.edit', $kelas->id) }}" class="badge bg-secondary-subtle text-secondary text-decoration-none">
                                        {{ $kelas->pengajar->name ?? '-' }} — {{ $kelas->student->name ?? 'Slot Kosong' }} <i class="ri-edit-line"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1100px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap" style="width: 110px;">Waktu</th>
                                    <th class="text-nowrap">Pengajar</th>
                                    <th class="text-nowrap">Bidang</th>
                                    <th class="text-nowrap">Kategori</th>
                                    <th class="text-nowrap">Murid</th>
                                    <th class="text-nowrap" style="min-width: 340px;">Kehadiran</th>
                                    <th class="text-nowrap">Status</th>
                                    <th class="text-end text-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($slotRows as $row)
                                    @php $kelas = $row['kelas']; @endphp
                                    @if($row['isBreak'] && !$kelas)
                                        <tr class="table-secondary">
                                            <td class="text-nowrap fw-semibold">{{ $row['time'] }}</td>
                                            <td colspan="7" class="text-muted text-center"><i class="ri-cup-line"></i> Jam Istirahat</td>
                                        </tr>
                                    @elseif($kelas)
                                        <tr>
                                            <td class="text-nowrap fw-semibold">
                                                {{ $row['time'] }}{{ $kelas->end_time ? ' – '.$kelas->end_time->format('H:i') : '' }}
                                            </td>
                                            <td class="text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
                                            <td class="text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                                            <td class="text-nowrap">{{ $kelas->kategori->name ?? '-' }}</td>
                                            <td class="text-nowrap">{{ $kelas->student->name ?? 'Slot Kosong' }}</td>
                                            <td>
                                                <form action="{{ route('jadwal.kelas.attendance.update', $kelas->id) }}" method="POST" class="d-flex flex-nowrap align-items-center gap-2 mb-1">
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
                                            <td class="text-end text-nowrap">
                                                @if($kelas->start_time)
                                                    <form action="{{ route('jadwal.kelas.pengajar-reminder.send', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Kirim rekap jadwal ke pengajar {{ $kelas->pengajar->name ?? '' }} untuk tanggal {{ $kelas->start_time->format('d/m/Y') }}?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-light" title="Kirim reminder ke pengajar">
                                                            <i class="ri-notification-3-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('jadwal.kelas.edit', $kelas->id) }}" class="btn btn-sm btn-light">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <form action="{{ route('jadwal.kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @else
                                        <tr class="text-muted">
                                            <td class="text-nowrap fw-semibold">{{ $row['time'] }}</td>
                                            <td colspan="7">
                                                <a href="{{ route('jadwal.kelas.create', ['ruangan_id' => $activeRuanganId, 'start_time' => $dateStr.'T'.$row['time']]) }}" class="btn btn-sm btn-light text-muted">
                                                    <i class="ri-add-line"></i> Tambah sesi jam {{ $row['time'] }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Jam Operasional belum diatur untuk branch ini, grid slot tidak bisa ditampilkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Tab "Tanpa Ruangan" -- tidak ada konsep hari
                    tunggal yang masuk akal tanpa satu Ruangan spesifik,
                    jadi ditampilkan sebagai daftar flat (semua tanggal),
                    dipaginate. --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1000px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap">Pengajar</th>
                                    <th class="text-nowrap">Bidang</th>
                                    <th class="text-nowrap">Murid</th>
                                    <th class="text-nowrap">Mulai</th>
                                    <th class="text-nowrap">Selesai</th>
                                    <th class="text-nowrap">Status</th>
                                    <th class="text-end text-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($noRuanganList as $kelas)
                                    <tr>
                                        <td class="text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
                                        <td class="text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                                        <td class="text-nowrap">{{ $kelas->student->name ?? 'Slot Kosong' }}</td>
                                        <td class="text-nowrap">{{ $kelas->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                        <td class="text-nowrap">{{ $kelas->end_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $kelas->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kelas->status }}</span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('jadwal.kelas.edit', $kelas->id) }}" class="btn btn-sm btn-light" title="Isi ruangannya di sini">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('jadwal.kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Tidak ada Jadwal Kelas tanpa Ruangan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $noRuanganList->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
