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
                @if($kategori)
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}">{{ $mataPelajaran->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $kategori->name }}</li>
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
                        <h4 class="mb-1">Pengajar @if($kategori)<span class="text-muted fs-6 fw-normal">— {{ $kategori->name }}</span>@endif</h4>
                        <p class="text-muted mb-0">
                            @if($kategori)
                                Pengajar yang ditugaskan ke Kategori "{{ $kategori->name }}", beserta hari & jam ketersediaannya masing-masing.
                            @else
                                Semua Pengajar company Anda, lintas Kategori, beserta hari & jam ketersediaannya masing-masing.
                            @endif
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($kategori)
                            <a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Kategori
                            </a>
                        @endif
                        <a href="{{ route('jadwal.pengajar.create', array_filter(['jadwal_kategori_id' => $kategori->id ?? null])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Pengajar
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($kategori)
                        <input type="hidden" name="jadwal_kategori_id" value="{{ $kategori->id }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama pengajar..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.pengajar.index', array_filter(['jadwal_kategori_id' => $kategori->id ?? null])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Pengajar</th>
                                @unless($kategori)
                                    <th class="text-nowrap">Kategori</th>
                                @endunless
                                <th class="text-nowrap">Hari Bisa</th>
                                <th class="text-nowrap">Jam</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pengajarKategoris as $pk)
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $pk->pengajar->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $pk->pengajar->email ?? '' }}</div>
                                    </td>
                                    @unless($kategori)
                                        <td class="text-nowrap">
                                            <div>{{ $pk->kategori->name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $pk->kategori->mataPelajaran->name ?? '' }}</div>
                                        </td>
                                    @endunless
                                    <td class="text-nowrap">{{ $pk->hariBisaLabel() ?: '-' }}</td>
                                    <td class="text-nowrap">{{ $pk->jamRangeLabel() }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $pk->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $pk->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.student.create', [
                                            'jadwal_mata_pelajaran_id' => $pk->kategori->mataPelajaran->id ?? ($mataPelajaran->id ?? null),
                                            'pengajar_id' => $pk->pengajar_id,
                                            'jadwal_kategori_id' => $pk->jadwal_kategori_id,
                                        ]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Student
                                        </a>
                                        <a href="{{ route('jadwal.pengajar.edit', $pk->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.pengajar.destroy', $pk->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pengajar ini dari Kategori &quot;{{ $pk->kategori->name ?? '' }}&quot;? Jadwal Rutin/sesi yang sudah ada TIDAK ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $kategori ? 5 : 6 }}" class="text-center text-muted py-4">
                                        @if($kategori)
                                            Belum ada Pengajar di Kategori ini. Klik "Tambah Pengajar" untuk menambahkan yang pertama.
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
                    {{ $pengajarKategoris->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
