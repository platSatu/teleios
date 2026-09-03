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
            // prev/next hari) supaya konteks (ruangan_id/pengajar_id +
            // date) tidak pernah hilang pindah halaman -- lihat class
            // docblock App\Http\Controllers\Jadwal\JadwalKelasController.
            $dateStr = $date->toDateString();
        @endphp

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Jadwal Kelas</h4>
                        <p class="text-muted mb-0">Grid per hari -- klik "+" di slot kosong untuk menambah, klik tab untuk pindah Ruangan/Pengajar.</p>
                    </div>
                    @if($groupBy === 'pengajar')
                        <a href="{{ route('jadwal.kelas.create', ['pengajar_id' => $activePengajarId, 'group_by' => 'pengajar', 'date' => $dateStr]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Kelas
                        </a>
                    @else
                        <a href="{{ route('jadwal.kelas.create', ['ruangan_id' => $activeRuanganId, 'date' => $dateStr]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Kelas
                        </a>
                    @endif
                </div>

                {{-- Switch mode pengelompokan (Update 7 September 2026,
                permintaan user "papan jadwal vs jam operasional") --
                murni ganti sumbu tab di bawah, data & grid slot 30 menit
                yang dipakai sama persis (lihat class docblock). --}}
                <div class="btn-group btn-group-sm mb-2" role="group">
                    <a href="{{ route('jadwal.kelas.index', ['group_by' => 'ruangan', 'date' => $dateStr]) }}"
                        class="btn {{ $groupBy === 'ruangan' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        <i class="ri-door-open-line"></i> Per Ruangan
                    </a>
                    <a href="{{ route('jadwal.kelas.index', ['group_by' => 'pengajar', 'date' => $dateStr]) }}"
                        class="btn {{ $groupBy === 'pengajar' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        <i class="ri-user-voice-line"></i> Per Pengajar
                    </a>
                </div>

                @if($groupBy === 'pengajar')
                    {{-- ================= MODE: PER PENGAJAR ================= --}}
                    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
                        @forelse($pengajars as $p)
                            <li class="nav-item">
                                <a href="{{ route('jadwal.kelas.index', ['group_by' => 'pengajar', 'pengajar_id' => $p->id, 'date' => $dateStr]) }}"
                                    class="nav-link {{ $activePengajarId === $p->id ? 'active' : '' }}">
                                    <i class="ri-user-voice-line"></i> {{ $p->name }}
                                </a>
                            </li>
                        @empty
                            <li class="nav-item">
                                <span class="text-muted small align-self-center">Belum ada Team Member yang ditandai sebagai Pengajar (role is_pengajar) -- atur dulu lewat menu Team/Roles.</span>
                            </li>
                        @endforelse
                    </ul>

                    @if($activePengajar)
                        <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <input type="hidden" name="group_by" value="pengajar">
                            <input type="hidden" name="pengajar_id" value="{{ $activePengajarId }}">
                            <a href="{{ route('jadwal.kelas.index', ['group_by' => 'pengajar', 'pengajar_id' => $activePengajarId, 'date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-light btn-sm" title="Hari sebelumnya">
                                <i class="ri-arrow-left-s-line"></i>
                            </a>
                            <input type="date" name="date" value="{{ $dateStr }}" class="form-control form-control-sm" style="width: 160px;" onchange="this.form.submit()">
                            <a href="{{ route('jadwal.kelas.index', ['group_by' => 'pengajar', 'pengajar_id' => $activePengajarId, 'date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-light btn-sm" title="Hari berikutnya">
                                <i class="ri-arrow-right-s-line"></i>
                            </a>
                            @if($dateStr !== now()->toDateString())
                                <a href="{{ route('jadwal.kelas.index', ['group_by' => 'pengajar', 'pengajar_id' => $activePengajarId, 'date' => now()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">Hari Ini</a>
                            @endif
                            <span class="text-muted small ms-2">{{ $date->translatedFormat('l, d F Y') }}</span>
                        </form>

                        @if(!$branchSetting)
                            <div class="alert alert-warning">
                                <i class="ri-error-warning-line"></i> Branch untuk Pengajar "{{ $activePengajar->name }}" belum diatur Jam Operasionalnya -- grid di bawah pakai jam default 08:00-20:00 sementara. <a href="{{ route('jadwal.branch.index') }}">Atur Jam Operasional</a> supaya grid sesuai jam buka toko sebenarnya.
                            </div>
                        @endif

                        @if($noTimeForPengajar->isNotEmpty())
                            <div class="mb-3">
                                <div class="text-muted small mb-1"><i class="ri-time-line"></i> Sesi pengajar ini yang belum punya jam (tidak terikat tanggal manapun sampai diisi):</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($noTimeForPengajar as $kelas)
                                        <a href="{{ route('jadwal.kelas.edit', ['id' => $kelas->id, 'group_by' => 'pengajar']) }}" class="badge bg-secondary-subtle text-secondary text-decoration-none">
                                            {{ $kelas->ruangan->name ?? 'Tanpa Ruangan' }} — {{ $kelas->student->name ?? 'Slot Kosong' }} <i class="ri-edit-line"></i>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @include('jadwal.jadwal-kelas._slot-grid-table', [
                            'slotRows' => $slotRows,
                            'groupBy' => $groupBy,
                            'dateStr' => $dateStr,
                            'activeRuanganId' => null,
                            'activePengajarId' => $activePengajarId,
                        ])
                    @endif
                @else
                    {{-- ================= MODE: PER RUANGAN ================= --}}
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
                                        <a href="{{ route('jadwal.kelas.edit', ['id' => $kelas->id, 'group_by' => 'ruangan']) }}" class="badge bg-secondary-subtle text-secondary text-decoration-none">
                                            {{ $kelas->pengajar->name ?? '-' }} — {{ $kelas->student->name ?? 'Slot Kosong' }} <i class="ri-edit-line"></i>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @include('jadwal.jadwal-kelas._slot-grid-table', [
                            'slotRows' => $slotRows,
                            'groupBy' => $groupBy,
                            'dateStr' => $dateStr,
                            'activeRuanganId' => $activeRuanganId,
                            'activePengajarId' => null,
                        ])
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
                                                <a href="{{ route('jadwal.kelas.edit', ['id' => $kelas->id, 'group_by' => 'ruangan']) }}" class="btn btn-sm btn-light" title="Isi ruangannya di sini">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <form action="{{ route('jadwal.kelas.destroy', ['id' => $kelas->id, 'group_by' => 'ruangan']) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
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
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
