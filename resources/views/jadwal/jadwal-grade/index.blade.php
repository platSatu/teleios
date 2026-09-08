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
                        <h4 class="mb-1">Grade <span class="text-muted fs-6 fw-normal">— {{ $kategori->mataPelajaran->name }} / {{ $kategori->name }}</span></h4>
                        <p class="text-muted mb-0">Level di bawah kategori ini, masing-masing dengan harga & split fee sendiri (mis. Grade A, B, C).</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $kategori->jadwal_mata_pelajaran_id]) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Kategori
                        </a>
                        <a href="{{ route('jadwal.grade.create', ['jadwal_kategori_id' => $kategoriId]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Grade
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <input type="hidden" name="jadwal_kategori_id" value="{{ $kategoriId }}">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama grade..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.grade.index', ['jadwal_kategori_id' => $kategoriId]) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Grade</th>
                                <th>Harga Bulanan</th>
                                <th>Split Company / Pengajar</th>
                                <th>Jadwal Rutin Aktif</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grades as $grade)
                                <tr>
                                    <td class="fw-semibold">{{ $grade->name }}</td>
                                    <td>
                                        Rp {{ number_format($grade->harga_bulanan, 0, ',', '.') }}
                                        <div class="text-muted small">≈ Rp {{ number_format($grade->hargaPerSesi(), 0, ',', '.') }} / sesi</div>
                                    </td>
                                    <td>{{ rtrim(rtrim(number_format($grade->persentase_company, 2), '0'), '.') }}% / {{ rtrim(rtrim(number_format($grade->persentase_pengajar, 2), '0'), '.') }}%</td>
                                    <td>{{ $grade->jadwal_rutins_count }}</td>
                                    <td>
                                        <span class="badge {{ $grade->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $grade->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.pengajar.index', ['jadwal_grade_id' => $grade->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Pengajar
                                        </a>
                                        <a href="{{ route('jadwal.grade.edit', $grade->id) }}" class="btn btn-sm btn-light"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('jadwal.grade.destroy', $grade->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus grade ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada Grade. Tambahkan Grade pertama (mis. "Grade A") untuk kategori ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $grades->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
