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

        @if($branch)
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $branch->name }}</li>
                    <li class="breadcrumb-item active" aria-current="page">Mata Pelajaran / Bidang</li>
                </ol>
            </nav>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Mata Pelajaran / Bidang{{ $branch ? ' — '.$branch->name : '' }}</h4>
                        <p class="text-muted mb-0">Katalog bidang kursus (musik, bahasa, dll.) yang dipakai untuk mengelompokkan Jadwal Kelas.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($branch)
                            <a href="{{ route('jadwal.branch.index') }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Branch
                            </a>
                        @endif
                        <a href="{{ route('jadwal.mata-pelajaran.create', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Mata Pelajaran / Bidang
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($branchOfficeId)
                        <input type="hidden" name="branch_office_id" value="{{ $branchOfficeId }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status'))
                        <a href="{{ route('jadwal.mata-pelajaran.index', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        {{--
                            text-nowrap di semua header + isi baris di
                            bawah SENGAJA dipasang -- tanpa ini, header
                            seperti "Jumlah Pengajar" kepencet jadi 2
                            baris saat layar sempit (numpuk/berantakan)
                            alih-alih tabelnya yang scroll ke samping.
                            .table-responsive (pembungkus di atas) sudah
                            sedia overflow-x:auto; text-nowrap ini yang
                            bikin tabel benar-benar lebih lebar dari
                            container-nya supaya scroll itu kepakai,
                            bukan malah teks yang dipaksa muat.

                            Jumlah Kelas/Pengajar/Murid (3 kolom
                            terpisah sebelumnya) digabung jadi satu
                            kolom "Statistik" berisi badge kecil berikon
                            -- lebih ringkas, kurangi total kolom dari 8
                            jadi 6.
                        --}}
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th class="text-nowrap">Nama</th>
                                <th class="text-nowrap">Branch</th>
                                <th class="text-nowrap">Statistik</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mataPelajarans as $mataPelajaran)
                                <tr>
                                    <td style="width: 48px;">
                                        @if ($mataPelajaran->image_url)
                                            <img src="{{ $mataPelajaran->image_url }}" alt="" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                        @else
                                            <span class="avatar avatar-md rounded d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary">
                                                <i class="uil uil-book-alt"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-nowrap">{{ $mataPelajaran->name }}</td>
                                    <td class="text-nowrap">{{ $mataPelajaran->branchOffice->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        <button type="button"
                                            class="btn btn-link p-0 border-0 text-decoration-none d-flex gap-1"
                                            data-bs-toggle="modal" data-bs-target="#rosterModal{{ $mataPelajaran->id }}"
                                            title="Lihat pengajar, murid & ruangan">
                                            <span class="badge bg-light text-dark border fw-normal" title="Jumlah Kelas">
                                                <i class="ri-book-2-line align-middle"></i> {{ $mataPelajaran->kelas_count }}
                                            </span>
                                            <span class="badge bg-light text-dark border fw-normal" title="Jumlah Pengajar">
                                                <i class="ri-user-star-line align-middle"></i> {{ $mataPelajaran->pengajar_count }}
                                            </span>
                                            <span class="badge bg-light text-dark border fw-normal" title="Jumlah Murid">
                                                <i class="ri-graduation-cap-line align-middle"></i> {{ $mataPelajaran->student_count }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $mataPelajaran->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $mataPelajaran->status }}</span>
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-price-tag-3-line"></i> Kategori
                                        </a>
                                        <a href="{{ route('jadwal.pengajar.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Pengajar
                                        </a>
                                        <a href="{{ route('jadwal.mata-pelajaran.edit', $mataPelajaran->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.mata-pelajaran.destroy', $mataPelajaran->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Mata Pelajaran / Bidang ini? Jadwal Kelas yang terhubung tidak ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada Mata Pelajaran / Bidang. Klik "Tambah Mata Pelajaran / Bidang" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $mataPelajarans->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>

        {{-- Detail Pengajar/Murid/Ruangan per Mata Pelajaran, dibuka dari
             klik badge Statistik di atas -- lihat
             JadwalMataPelajaranController::attachRoster(). --}}
        @foreach($mataPelajarans as $mataPelajaran)
            <div class="modal fade" id="rosterModal{{ $mataPelajaran->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $mataPelajaran->name }} — Pengajar, Murid & Ruangan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @if($mataPelajaran->roster->isEmpty())
                                <p class="text-muted mb-0">Belum ada Jadwal Kelas aktif untuk topik ini.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-nowrap">Pengajar</th>
                                                <th class="text-nowrap">Murid</th>
                                                <th class="text-nowrap">Ruangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($mataPelajaran->roster as $kelas)
                                                <tr>
                                                    <td class="text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
                                                    <td class="text-nowrap">{{ $kelas->student->name ?? '-' }}</td>
                                                    <td class="text-nowrap">{{ $kelas->ruangan->name ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($mataPelajaran->roster_truncated_count > 0)
                                    <p class="text-muted small mt-2 mb-0">
                                        + {{ $mataPelajaran->roster_truncated_count }} penugasan lainnya tidak ditampilkan.
                                    </p>
                                @endif
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
