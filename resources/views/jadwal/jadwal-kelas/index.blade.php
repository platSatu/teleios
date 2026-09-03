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

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Jadwal Kelas</h4>
                        <p class="text-muted mb-0">Semua sesi -- pengajar, ruangan, dan murid. Filter Tanggal/Pengajar/Bidang di bawah.</p>
                    </div>
                    <a href="{{ route('jadwal.kelas.create', ['date' => $filterDate, 'pengajar_id' => $filterPengajarId, 'jadwal_mata_pelajaran_id' => $filterMataPelajaranId]) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Jadwal Kelas
                    </a>
                </div>

                {{-- Filter -- Tanggal/Pengajar/Mata Pelajaran, sama pola
                dengan filter di JadwalStudentController::index(). Select
                auto-submit; Tanggal dikosongkan (tombol "Semua Tanggal")
                utk lihat lintas tanggal. --}}
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <input type="date" name="date" value="{{ $filterDate }}" class="form-control form-control-sm" style="width: 160px;" onchange="this.form.submit()">
                    <select name="pengajar_id" class="form-select form-select-sm" style="width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Pengajar</option>
                        @foreach($pengajars as $p)
                            <option value="{{ $p->id }}" @selected($filterPengajarId === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <select name="jadwal_mata_pelajaran_id" class="form-select form-select-sm" style="width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Bidang / Mata Pelajaran</option>
                        @foreach($mataPelajarans as $mp)
                            <option value="{{ $mp->id }}" @selected($filterMataPelajaranId === $mp->id)>{{ $mp->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="ri-filter-line"></i> Filter</button>
                    @if($filterDate || $filterPengajarId || $filterMataPelajaranId)
                        <a href="{{ route('jadwal.kelas.index') }}" class="btn btn-light btn-sm">Reset Filter</a>
                    @endif
                    @if($filterDate)
                        <a href="{{ route('jadwal.kelas.index', ['date' => '', 'pengajar_id' => $filterPengajarId, 'jadwal_mata_pelajaran_id' => $filterMataPelajaranId]) }}" class="text-muted small">Semua Tanggal</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1100px;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center text-nowrap">Pengajar</th>
                                <th class="text-center text-nowrap">Ruangan</th>
                                <th class="text-center text-nowrap">Bidang</th>
                                <th class="text-center text-nowrap">Kategori</th>
                                <th class="text-center text-nowrap">Murid</th>
                                <th class="text-center text-nowrap">Mulai</th>
                                <th class="text-center text-nowrap">Selesai</th>
                                <th class="text-center text-nowrap" style="min-width: 340px;">Kehadiran</th>
                                <th class="text-center text-nowrap">Status</th>
                                <th class="text-center text-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sesiList as $kelas)
                                <tr>
                                    <td class="text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->ruangan->name ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->kategori->name ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->student->name ?? 'Slot Kosong' }}</td>
                                    <td class="text-nowrap">{{ $kelas->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->end_time?->format('d/m/Y H:i') ?? '-' }}</td>
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
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">Tidak ada Jadwal Kelas yang cocok dengan filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $sesiList->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
