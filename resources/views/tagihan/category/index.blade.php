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
                        <h4 class="mb-1">Kategori Tagihan</h4>
                        <p class="text-muted mb-0">Jenis tagihan per branch (mis. Uang Sekolah, Uang Pangkal). Aturan denda bertingkat dikelola dari halaman edit tiap kategori.</p>
                    </div>
                    <a href="{{ route('tagihan.category.create', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Kategori
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Branch</th>
                                <th>Jumlah Tagihan Dibuat</th>
                                <th>Denda</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                                <tr>
                                    <td class="fw-semibold">{{ $category->name }}</td>
                                    <td>{{ $category->branchOffice->name ?? '-' }}</td>
                                    <td>{{ $category->tagihan_count }}</td>
                                    <td>
                                        <span class="badge {{ $category->denda_enabled ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $category->denda_enabled ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('tagihan.category.edit', $category->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i> Kelola
                                        </a>
                                        <form action="{{ route('tagihan.category.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada Kategori Tagihan. Klik "Tambah Kategori" untuk membuat yang pertama.</td>
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
