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
                        <h4 class="mb-1">
                            Ruangan
                            @if($branch)
                                <span class="text-muted fs-6 fw-normal">— {{ $branch->name }}</span>
                            @endif
                        </h4>
                        <p class="text-muted mb-0">Daftar ruangan yang tersedia di branch ini. Murni info, tidak dikunci ke kelas tertentu.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('jadwal.branch.index') }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Branch
                        </a>
                        <a href="{{ route('jadwal.ruangan.create', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Ruangan
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($branchOfficeId)
                        <input type="hidden" name="branch_office_id" value="{{ $branchOfficeId }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama ruangan..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if(request('search'))
                        <a href="{{ route('jadwal.ruangan.index', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Ruangan</th>
                                @if(!$branch)
                                    <th>Branch</th>
                                @endif
                                <th>Catatan Kegunaan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ruangans as $ruangan)
                                <tr>
                                    <td class="fw-semibold">{{ $ruangan->name }}</td>
                                    @if(!$branch)
                                        <td>{{ $ruangan->branchOffice?->name ?? '-' }}</td>
                                    @endif
                                    <td class="text-muted">{{ \Illuminate\Support\Str::limit($ruangan->catatan_kegunaan, 60) ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $ruangan->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $ruangan->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.ruangan.edit', $ruangan->id) }}" class="btn btn-sm btn-light"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('jadwal.ruangan.destroy', $ruangan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus ruangan ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $branch ? 4 : 5 }}" class="text-center text-muted py-4">Belum ada ruangan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $ruangans->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
