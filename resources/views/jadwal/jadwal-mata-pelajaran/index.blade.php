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
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Nama</th>
                                <th>Branch</th>
                                <th>Jumlah Kelas</th>
                                <th>Jumlah Pengajar</th>
                                <th>Jumlah Murid</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
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
                                    <td class="fw-semibold">{{ $mataPelajaran->name }}</td>
                                    <td>{{ $mataPelajaran->branchOffice->name ?? '-' }}</td>
                                    <td>{{ $mataPelajaran->kelas_count }}</td>
                                    <td>{{ $mataPelajaran->pengajar_count }}</td>
                                    <td>{{ $mataPelajaran->student_count }}</td>
                                    <td>
                                        <span class="badge {{ $mataPelajaran->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $mataPelajaran->status }}</span>
                                    </td>
                                    <td class="text-end">
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
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada Mata Pelajaran / Bidang. Klik "Tambah Mata Pelajaran / Bidang" untuk membuat yang pertama.</td>
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
    </div>
</div>
@endsection
