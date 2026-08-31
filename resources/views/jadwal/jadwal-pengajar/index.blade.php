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
                <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $mataPelajaran->name }}</li>
                <li class="breadcrumb-item active" aria-current="page">Pengajar</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Pengajar — {{ $mataPelajaran->name }}</h4>
                        <p class="text-muted mb-0">Anggota tim company yang bisa dijadikan pengajar. Halaman ini khusus menampilkan pengajar (diambil dari Team Members) — pilih salah satu untuk menambahkan Student baru.</p>
                    </div>
                    <a href="{{ route('jadwal.mata-pelajaran.index', array_filter(['branch_office_id' => $mataPelajaran->branch_office_id])) }}" class="btn btn-light">
                        <i class="ri-arrow-left-line"></i> Kembali ke Mata Pelajaran / Bidang
                    </a>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaran->id }}">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama pengajar..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.pengajar.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id]) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamMembers as $index => $member)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="fw-semibold">{{ $member->name }}</td>
                                    <td>{{ $member->email }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.student.create', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id, 'pengajar_id' => $member->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Student
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada anggota tim di branch/company ini.</td>
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
