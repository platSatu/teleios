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

        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                @if($grade)
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}">{{ $mataPelajaran->name }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.grade.index', ['jadwal_kategori_id' => $kategori->id]) }}">{{ $kategori->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $grade->name }}</li>
                    <li class="breadcrumb-item active" aria-current="page">Pengajar</li>
                @else
                    <li class="breadcrumb-item active" aria-current="page">Pengajar</li>
                @endif
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Pengajar @if($grade)<span class="text-muted fs-6 fw-normal">— {{ $kategori->name }} / {{ $grade->name }}</span>@endif</h4>
                        <p class="text-muted mb-0">
                            @if($grade)
                                Pengajar yang ditugaskan ke Grade "{{ $grade->name }}", beserta hari & jam ketersediaannya masing-masing.
                            @else
                                Semua Pengajar company Anda, lintas Grade, beserta hari & jam ketersediaannya masing-masing.
                            @endif
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($grade)
                            <a href="{{ route('jadwal.grade.index', ['jadwal_kategori_id' => $kategori->id]) }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Grade
                            </a>
                        @endif
                        <a href="{{ route('jadwal.pengajar.create', array_filter(['jadwal_grade_id' => $grade->id ?? null])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Pengajar
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($grade)
                        <input type="hidden" name="jadwal_grade_id" value="{{ $grade->id }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama pengajar..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.pengajar.index', array_filter(['jadwal_grade_id' => $grade->id ?? null])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Pengajar</th>
                                @unless($grade)
                                    <th class="text-nowrap">Grade</th>
                                @endunless
                                <th class="text-nowrap">Hari &amp; Jam Tersedia</th>
                                <th class="text-nowrap text-center">Murid</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pengajarGrades as $pg)
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $pg->pengajar->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $pg->pengajar->email ?? '' }}</div>
                                    </td>
                                    @unless($grade)
                                        <td class="text-nowrap">
                                            <div>{{ $pg->grade->name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $pg->grade->kategori->name ?? '' }} — {{ $pg->grade->kategori->mataPelajaran->name ?? '' }}</div>
                                        </td>
                                    @endunless
                                    <td>
                                        @php $jadwalGroups = $pg->jadwalGroupedByHari(); @endphp
                                        @if($jadwalGroups->isEmpty())
                                            <span class="text-muted">-</span>
                                        @else
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($jadwalGroups as $group)
                                                    <div class="text-nowrap"><span class="fw-semibold">{{ $group['label'] }}</span>: {{ implode(', ', $group['ranges']) }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        {{-- Jumlah murid pengajar ini di Grade ini (lihat docblock
                                        JadwalPengajarController::attachMuridCounts()) -- diklik
                                        pindah ke list Student yang sudah di-filter, bukan modal. --}}
                                        <a href="{{ route('jadwal.student.index', [
                                            'jadwal_mata_pelajaran_id' => $pg->grade->kategori->mataPelajaran->id ?? ($mataPelajaran->id ?? null),
                                            'pengajar_id' => $pg->pengajar_id,
                                            'jadwal_grade_id' => $pg->jadwal_grade_id,
                                        ]) }}" class="badge bg-light text-dark border fw-normal text-decoration-none" title="Lihat murid pengajar ini">
                                            <i class="ri-graduation-cap-line align-middle"></i> {{ $pg->murid_count ?? 0 }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $pg->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $pg->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.student.create', [
                                            'jadwal_mata_pelajaran_id' => $pg->grade->kategori->mataPelajaran->id ?? ($mataPelajaran->id ?? null),
                                            'pengajar_id' => $pg->pengajar_id,
                                            'jadwal_grade_id' => $pg->jadwal_grade_id,
                                        ]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Student
                                        </a>
                                        <a href="{{ route('jadwal.pengajar.edit', $pg->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.pengajar.destroy', $pg->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pengajar ini dari Grade &quot;{{ $pg->grade->name ?? '' }}&quot;? Jadwal Rutin/sesi yang sudah ada TIDAK ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $grade ? 5 : 6 }}" class="text-center text-muted py-4">
                                        @if($grade)
                                            Belum ada Pengajar di Grade ini. Klik "Tambah Pengajar" untuk menambahkan yang pertama.
                                        @else
                                            Belum ada Pengajar. Klik "Tambah Pengajar" untuk menambahkan yang pertama.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $pengajarGrades->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
