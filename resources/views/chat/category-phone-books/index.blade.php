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

        {{-- Import result — flashed by CategoryPhoneBookController::import(). --}}
        @if (session('importResult'))
            @php $importResult = session('importResult'); @endphp
            <div class="alert {{ empty($importResult['errors']) ? 'alert-success' : 'alert-warning' }}">
                <div class="fw-semibold mb-1">
                    Import selesai: {{ count($importResult['created']) }} kelompok berhasil dibuat{{ empty($importResult['errors']) ? '.' : ', ' . count($importResult['errors']) . ' baris gagal.' }}
                </div>
                @if (!empty($importResult['errors']))
                    <details open>
                        <summary class="small text-muted" style="cursor: pointer;">Lihat baris yang gagal</summary>
                        <ul class="small mb-0 mt-2">
                            @foreach ($importResult['errors'] as $err)
                                <li>Baris {{ $err['row'] }}{{ $err['name'] ? ' (' . $err['name'] . ')' : '' }}: {{ implode(' ', $err['messages']) }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Kelompok</h4>
                        <p class="text-muted mb-0">Kelompokkan Buku Telepon kamu — dipakai juga sebagai pilihan tujuan pengiriman.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#importCategoryPhoneBookModal">
                            <i class="ri-upload-2-line"></i> Import
                        </button>
                        <a href="{{ route('chat.category-phone-books.create') }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Kelompok
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama kelompok..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status'))
                        <a href="{{ route('chat.category-phone-books.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Kelompok</th>
                                <th>Branch</th>
                                <th>Jumlah Kontak</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                                <tr>
                                    <td class="fw-semibold">{{ $category->name }}</td>
                                    <td>{{ $category->branchOffice->name ?? '-' }}</td>
                                    <td>{{ $category->phone_books_count }}</td>
                                    <td>
                                        <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('chat.category-phone-books.edit', $category->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('chat.category-phone-books.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kelompok ini? Kontak di dalamnya tidak ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada kelompok. Klik "Tambah Kelompok" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importCategoryPhoneBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('chat.category-phone-books.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Kelompok dari Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload file <code>.xlsx</code>/<code>.xls</code>/<code>.csv</code> untuk menambah banyak
                        kelompok sekaligus. Kolom yang dibutuhkan: <code>nama</code>, <code>branch</code> (opsional),
                        dan <code>status</code>.
                    </p>

                    <a href="{{ route('chat.category-phone-books.import-template') }}" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="ri-file-download-line"></i> Download Template
                    </a>

                    <div class="mb-1">
                        <label for="import_file_category" class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="import_file_category" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Maks 2MB, maks {{ \App\Imports\CategoryPhoneBookImport::MAX_ROWS }} kelompok per file.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-upload-2-line"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->has('file'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('importCategoryPhoneBookModal');
            if (modalEl && window.bootstrap) {
                new bootstrap.Modal(modalEl).show();
            }
        });
    </script>
@endif
@endsection
