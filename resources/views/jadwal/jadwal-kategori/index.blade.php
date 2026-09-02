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
                        <h4 class="mb-1">Kategori <span class="text-muted fs-6 fw-normal">— {{ $mataPelajaran->name }}</span></h4>
                        <p class="text-muted mb-0">Level/varian di bawah kelas ini, masing-masing dengan harga & split fee sendiri.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Kelas
                        </a>
                        <a href="{{ route('jadwal.kategori.create', ['jadwal_mata_pelajaran_id' => $mataPelajaranId]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Kategori
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaranId }}">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama kategori..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaranId]) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Kategori</th>
                                <th>Harga / Sesi</th>
                                <th>Split Company / Pengajar</th>
                                <th>Jadwal Rutin Aktif</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kategoris as $kategori)
                                <tr>
                                    <td class="fw-semibold">{{ $kategori->name }}</td>
                                    <td>Rp {{ number_format($kategori->harga_per_sesi, 0, ',', '.') }}</td>
                                    <td>{{ rtrim(rtrim(number_format($kategori->persentase_company, 2), '0'), '.') }}% / {{ rtrim(rtrim(number_format($kategori->persentase_pengajar, 2), '0'), '.') }}%</td>
                                    <td>{{ $kategori->jadwal_rutins_count }}</td>
                                    <td>
                                        <span class="badge {{ $kategori->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kategori->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.kategori.edit', $kategori->id) }}" class="btn btn-sm btn-light"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('jadwal.kategori.destroy', $kategori->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada kategori. Tambahkan level/varian pertama untuk kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $kategoris->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
