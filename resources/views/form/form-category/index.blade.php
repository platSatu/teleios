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
                    <li class="breadcrumb-item"><a href="{{ route('form.branch.index') }}">Branch</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $branch->name }}</li>
                    <li class="breadcrumb-item active" aria-current="page">Form Category</li>
                </ol>
            </nav>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Form Category{{ $branch ? ' — '.$branch->name : '' }}</h4>
                        <p class="text-muted mb-0">Kelompok form (mis. Pendaftaran, Survey) per branch. Tombol <strong>Copy</strong> menduplikasi seluruh rangkaian di dalamnya (Form Header sampai Setting).</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($branch)
                            <a href="{{ route('form.branch.index') }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Branch
                            </a>
                        @endif
                        <a href="{{ route('form.category.create', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Form Category
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
                        <a href="{{ route('form.category.index', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Nama</th>
                                <th class="text-nowrap">Branch</th>
                                <th class="text-nowrap">Jumlah Form</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $category->name }}</td>
                                    <td class="text-nowrap">{{ $category->branchOffice->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-light text-dark border fw-normal" title="Jumlah Form Header">
                                            <i class="ri-file-list-3-line align-middle"></i> {{ $category->headers_count }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('form.header.index', $category->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Lihat Form Header
                                        </a>
                                        <form action="{{ route('form.category.duplicate', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Duplikasi Form Category ini beserta seluruh Header/Content/Footer/Setting di dalamnya? Hasil copy akan berstatus Inactive.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-light" title="Copy"><i class="ri-file-copy-line"></i></button>
                                        </form>
                                        <a href="{{ route('form.category.edit', $category->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('form.category.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Form Category ini? Seluruh Form Header/Content/Footer/Setting di dalamnya ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada Form Category. Klik "Tambah Form Category" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $categories->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
