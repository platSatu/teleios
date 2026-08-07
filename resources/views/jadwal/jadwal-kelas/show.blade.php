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
                <h4 class="mb-1">{{ $jadwalKelas->name ?: $jadwalKelas->mataPelajaran->name }}</h4>
                <p class="text-muted mb-0">
                    {{ $jadwalKelas->mataPelajaran->name ?? '-' }} &middot;
                    {{ $jadwalKelas->branchOffice->name ?? '-' }} &middot;
                    {{ $jadwalKelas->hari }}, {{ substr($jadwalKelas->jam_mulai, 0, 5) }}-{{ substr($jadwalKelas->jam_selesai, 0, 5) }} &middot;
                    Guru:
                    @if ($jadwalKelas->guru)
                        <a href="{{ route('jadwal.jadwal-kelas.guru.show', $jadwalKelas->guru_user_id) }}">{{ $jadwalKelas->guru->name }}</a>
                    @else
                        Belum ditentukan
                    @endif
                </p>
            </div>
            <a href="{{ route('jadwal.jadwal-kelas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0">Murid Terdaftar ({{ $jadwalKelas->murid->where('status', 'active')->count() }})</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#enrollMuridModal">
                                <i class="ri-user-add-line"></i> Tambah
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama</th>
                                        <th>No. WA</th>
                                        <th>Status</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($jadwalKelas->murid as $enrollment)
                                        <tr>
                                            <td>{{ $enrollment->murid->name ?? '-' }}</td>
                                            <td class="text-muted small">{{ $enrollment->murid->handphone ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $enrollment->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $enrollment->status }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if ($enrollment->status === 'active')
                                                    <form action="{{ route('jadwal.jadwal-kelas.murid.destroy', [$jadwalKelas->id, $enrollment->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Keluarkan murid ini dari jadwal kelas?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="Keluarkan"><i class="ri-user-unfollow-line"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada murid terdaftar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0">Sesi Kelas</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#generateSesiModal">
                                <i class="ri-calendar-2-line"></i> Generate Sesi
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th>Status Kelas</th>
                                        <th>Guru</th>
                                        <th class="text-end">Murid / Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($sesi as $s)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Carbon::parse($s->tanggal)->format('d M Y') }}</td>
                                            <td class="{{ $s->jam_mulai_override ? 'text-warning fw-semibold' : 'text-muted small' }}">
                                                {{ substr($s->jam_mulai_override ?: $jadwalKelas->jam_mulai, 0, 5) }}-{{ substr($s->jam_selesai_override ?: $jadwalKelas->jam_selesai, 0, 5) }}
                                            </td>
                                            <td>
                                                <span class="badge
                                                    @if($s->status === 'terjadwal') bg-info-subtle text-info
                                                    @elseif($s->status === 'berjalan') bg-success-subtle text-success
                                                    @elseif($s->status === 'dipindah') bg-warning-subtle text-warning
                                                    @else bg-secondary-subtle text-secondary
                                                    @endif">
                                                    {{ $s->status }}
                                                </span>
                                                @if ($s->guru_confirmed_at)
                                                    <div class="text-success small mt-1"><i class="ri-checkbox-circle-fill"></i> {{ $s->guru_confirmed_at->format('d M H:i') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($s->guru_status === 'sakit')
                                                    <span class="badge bg-danger-subtle text-danger">Sakit</span>
                                                @elseif ($s->guru_status === 'diganti')
                                                    <span class="badge bg-warning-subtle text-warning">Diganti: {{ $s->guruPengganti->name ?? '-' }}</span>
                                                @else
                                                    {{ $jadwalKelas->guru->name ?? '-' }}
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('jadwal.jadwal-kelas.show', $jadwalKelas->id) }}#sesi-{{ $s->id }}" class="btn btn-sm btn-light" title="Lihat detail kehadiran">
                                                    {{ $s->murid_sesi_count }} murid
                                                </a>
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
                                                            <h5 class="modal-title">Ubah Jam — {{ \Illuminate\Support\Carbon::parse($s->tanggal)->format('d M Y') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="text-muted small">Berlaku khusus tanggal ini saja. Guru & murid otomatis mendapat notifikasi WA.</p>
                                                            <div class="row">
                                                                <div class="col-6 mb-3">
                                                                    <label class="form-label">Jam Mulai Baru</label>
                                                                    <input type="time" name="jam_mulai_override" class="form-control" value="{{ substr($s->jam_mulai_override ?: $jadwalKelas->jam_mulai, 0, 5) }}" required>
                                                                </div>
                                                                <div class="col-6 mb-3">
                                                                    <label class="form-label">Jam Selesai Baru</label>
                                                                    <input type="time" name="jam_selesai_override" class="form-control" value="{{ substr($s->jam_selesai_override ?: $jadwalKelas->jam_selesai, 0, 5) }}" required>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Catatan (opsional)</label>
                                                                <input type="text" name="catatan" class="form-control">
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
                                            <td colspan="5" class="text-center text-muted py-3">Belum ada sesi. Klik "Generate Sesi" untuk membuat jadwal tanggal.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2">
                            {{ $sesi->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($usulanPerubahan->isNotEmpty())
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Usulan Waktu Pengganti ke Guru (10 terakhir)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Murid</th>
                                    <th>Diusulkan</th>
                                    <th>Status</th>
                                    <th>Direspons</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($usulanPerubahan as $u)
                                    <tr>
                                        <td>{{ $u->murid->name ?? '-' }}</td>
                                        <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($u->tanggal_usulan)->format('d M Y') }}, {{ substr($u->jam_mulai_usulan, 0, 5) }}-{{ substr($u->jam_selesai_usulan, 0, 5) }}</td>
                                        <td>
                                            <span class="badge
                                                @if($u->status === 'pending') bg-info-subtle text-info
                                                @elseif($u->status === 'disetujui') bg-success-subtle text-success
                                                @elseif($u->status === 'bentrok') bg-danger-subtle text-danger
                                                @else bg-secondary-subtle text-secondary
                                                @endif">
                                                {{ $u->status }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">{{ $u->responded_at ? $u->responded_at->format('d M H:i') : 'Menunggu balasan guru' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Per-sesi detail: attendance per murid, with manual override --}}
        @foreach ($sesi as $s)
            <div class="card mb-3" id="sesi-{{ $s->id }}">
                <div class="card-body">
                    <h6 class="mb-3">Detail Kehadiran — {{ \Illuminate\Support\Carbon::parse($s->tanggal)->format('d M Y') }} ({{ $jadwalKelas->hari }})</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Murid</th>
                                    <th>Status</th>
                                    <th>Konfirmasi</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($s->muridSesi as $sm)
                                    <tr>
                                        <td>{{ $sm->jadwalKelasMurid->murid->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge
                                                @if($sm->status === 'hadir') bg-success-subtle text-success
                                                @elseif(in_array($sm->status, ['izin', 'pindah_hari'])) bg-warning-subtle text-warning
                                                @elseif($sm->status === 'tidak_ada_kabar') bg-danger-subtle text-danger
                                                @else bg-secondary-subtle text-secondary
                                                @endif">
                                                {{ str_replace('_', ' ', $sm->status) }}
                                            </span>
                                            @if ($sm->tanggal_pindah)
                                                <span class="text-muted small">&rarr; {{ \Illuminate\Support\Carbon::parse($sm->tanggal_pindah)->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">
                                            @if ($sm->confirmed_at)
                                                {{ $sm->confirmed_at->format('d M H:i') }} ({{ $sm->confirmation_channel === 'wa_reply' ? 'balasan WA' : 'manual' }})
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('jadwal.jadwal-kelas.sesi-murid.alternatif', $sm->id) }}" class="btn btn-sm btn-light" title="Cari jadwal pengganti (murid tidak bisa hadir)">
                                                <i class="ri-calendar-2-line"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#updateStatusModal-{{ $sm->id }}" title="Update manual">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="updateStatusModal-{{ $sm->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('jadwal.jadwal-kelas.sesi-murid.update-status', $sm->id) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Status — {{ $sm->jadwalKelasMurid->murid->name ?? '-' }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select name="status" class="form-select" required>
                                                                <option value="terjadwal" @selected($sm->status === 'terjadwal')>Terjadwal</option>
                                                                <option value="hadir" @selected($sm->status === 'hadir')>Hadir</option>
                                                                <option value="izin" @selected($sm->status === 'izin')>Izin</option>
                                                                <option value="pindah_hari" @selected($sm->status === 'pindah_hari')>Pindah Hari</option>
                                                                <option value="tidak_ada_kabar" @selected($sm->status === 'tidak_ada_kabar')>Tidak Ada Kabar</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Tanggal Pindah (jika pindah hari)</label>
                                                            <input type="date" name="tanggal_pindah" class="form-control" value="{{ $sm->tanggal_pindah }}">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Catatan</label>
                                                            <textarea name="catatan" class="form-control" rows="2">{{ $sm->catatan }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

    </div>
</div>

{{-- Enroll murid modal --}}
<div class="modal fade" id="enrollMuridModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('jadwal.jadwal-kelas.murid.store', $jadwalKelas->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Murid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Pilih Murid</label>
                    <select name="murid_user_id" class="form-select" required>
                        <option value="">-- Pilih Murid --</option>
                        @foreach ($availableMurid as $murid)
                            <option value="{{ $murid->id }}">{{ $murid->name }}{{ $murid->handphone ? ' — ' . $murid->handphone : '' }}</option>
                        @endforeach
                    </select>
                    @if ($availableMurid->isEmpty())
                        <div class="form-text text-warning">Semua member sudah terdaftar, atau belum ada member lain di cabang ini.</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Generate sesi modal --}}
<div class="modal fade" id="generateSesiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('jadwal.jadwal-kelas.sesi.generate', $jadwalKelas->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Generate Sesi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Membuat sesi untuk setiap tanggal "{{ $jadwalKelas->hari }}" di rentang berikut (maks 90 hari).</p>
                    <div class="mb-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="tanggal_mulai" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="tanggal_selesai" class="form-control" required value="{{ now()->addDays(30)->toDateString() }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
