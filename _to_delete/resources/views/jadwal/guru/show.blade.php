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
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="ri-user-star-line"></i> {{ $guru->name }}</h4>
                <p class="text-muted mb-0">{{ $guru->handphone ?? '-' }} &middot; Mengajar {{ $classes->count() }} kelas</p>
            </div>
            <a href="{{ route('jadwal.jadwal-kelas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali ke Jadwal Kelas
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Kelas yang Diajar</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                                <th>Cabang</th>
                                <th>Jadwal</th>
                                <th>Murid</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($classes as $c)
                                <tr>
                                    <td>{{ $c->name ?: '-' }}</td>
                                    <td>{{ $c->mataPelajaran->name ?? '-' }}</td>
                                    <td>{{ $c->branchOffice->name ?? '-' }}</td>
                                    <td class="text-muted small">{{ $c->hari }}, {{ substr($c->jam_mulai, 0, 5) }}-{{ substr($c->jam_selesai, 0, 5) }}</td>
                                    <td>{{ $c->murid_count }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.jadwal-kelas.show', $c->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-settings-3-line"></i> Kelola
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Belum mengajar kelas apapun.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Sesi Mendatang (30 sesi terdekat)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Kelas</th>
                                <th>Jam</th>
                                <th>Status Guru</th>
                                <th>Murid</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sesi as $s)
                                @php
                                    $jamMulai = substr((string) ($s->jam_mulai_override ?: $s->jadwalKelas->jam_mulai), 0, 5);
                                    $jamSelesai = substr((string) ($s->jam_selesai_override ?: $s->jadwalKelas->jam_selesai), 0, 5);
                                    $label = $s->jadwalKelas->name ?: $s->jadwalKelas->mataPelajaran->name ?? '-';
                                @endphp
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($s->tanggal)->format('d M Y') }}</td>
                                    <td>{{ $label }}</td>
                                    <td class="{{ $s->jam_mulai_override ? 'text-warning fw-semibold' : '' }}">
                                        {{ $jamMulai }}-{{ $jamSelesai }}
                                        @if ($s->jam_mulai_override)
                                            <i class="ri-time-line" title="Jam diubah khusus tanggal ini"></i>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($s->guru_status === 'sakit')
                                            <span class="badge bg-danger-subtle text-danger">Sakit — belum ada pengganti</span>
                                        @elseif ($s->guru_status === 'diganti')
                                            <span class="badge bg-warning-subtle text-warning">Digantikan: {{ $s->guruPengganti->name ?? '-' }}</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success">Normal</span>
                                        @endif
                                    </td>
                                    <td>{{ $s->murid_sesi_count }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light" title="Ubah jam (khusus tanggal ini)" data-bs-toggle="modal" data-bs-target="#rescheduleModal-{{ $s->id }}">
                                            <i class="ri-time-line"></i>
                                        </button>
                                        @if ($s->guru_status === 'normal')
                                            <form action="{{ route('jadwal.jadwal-kelas.sesi.mark-sakit', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tandai guru berhalangan (sakit) untuk sesi tanggal ini?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Tandai sakit & cari pengganti">
                                                    <i class="ri-user-unfollow-line"></i>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('jadwal.jadwal-kelas.sesi.pengganti', $s->id) }}" class="btn btn-sm btn-light" title="Lihat/atur pengganti">
                                                <i class="ri-user-search-line"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>

                                <div class="modal fade" id="rescheduleModal-{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('jadwal.jadwal-kelas.sesi.reschedule-time', $s->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Ubah Jam — {{ $label }} ({{ \Illuminate\Support\Carbon::parse($s->tanggal)->format('d M Y') }})</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted small">Perubahan ini hanya berlaku untuk tanggal ini saja (bukan jadwal rutin). Guru & murid otomatis mendapat notifikasi WA.</p>
                                                    <div class="row">
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label">Jam Mulai Baru</label>
                                                            <input type="time" name="jam_mulai_override" class="form-control" value="{{ $jamMulai }}" required>
                                                        </div>
                                                        <div class="col-6 mb-3">
                                                            <label class="form-label">Jam Selesai Baru</label>
                                                            <input type="time" name="jam_selesai_override" class="form-control" value="{{ $jamSelesai }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Catatan (opsional)</label>
                                                        <input type="text" name="catatan" class="form-control" placeholder="Misal: dimajukan karena guru ada urusan">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan & Kirim Notifikasi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Belum ada sesi mendatang. Generate sesi dulu dari halaman detail kelas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
