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
                        <h4 class="mb-1">Branch</h4>
                        <p class="text-muted mb-0">Titik awal Jadwal — pilih branch untuk menambahkan Mata Pelajaran / Bidang baru. Halaman ini khusus menampilkan branch (tanpa tambah/ubah/hapus di sini).</p>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama branch..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.branch.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Branch</th>
                                <th>Status</th>
                                <th>Jumlah Mata Pelajaran / Bidang</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($branches as $branch)
                                <tr>
                                    <td class="fw-semibold">{{ $branch->name }}</td>
                                    <td>
                                        <span class="badge {{ $branch->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $branch->status }}</span>
                                    </td>
                                    <td>{{ $branch->jadwal_mata_pelajaran_count }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.mata-pelajaran.index', ['branch_office_id' => $branch->id]) }}" class="btn btn-sm btn-light">
                                            Lihat Mata Pelajaran / Bidang
                                        </a>
                                        <a href="{{ route('jadwal.mata-pelajaran.create', ['branch_office_id' => $branch->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Mata Pelajaran / Bidang
                                        </a>
                                        <a href="{{ route('jadwal.ruangan.index', ['branch_office_id' => $branch->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-door-open-line"></i> Ruangan
                                        </a>
                                        <a href="{{ route('jadwal.branch-settings.edit', $branch->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-time-line"></i> Jam Operasional
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada branch. Tambahkan branch lewat menu Setting &gt; Branch Office terlebih dahulu.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $branches->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
