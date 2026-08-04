@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Help Center</h4>
                    <p class="text-muted mb-0">Ajukan komplain atau pertanyaan, dan pantau balasannya di sini.</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHelpCenterModal">
                    <i class="ri-add-line"></i> Buat Tiket
                </button>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari no. tiket/judul..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>No. Tiket</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Dibuat</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($helpCenters as $index => $item)
                            <tr>
                                <td>{{ $helpCenters->firstItem() + $index }}</td>
                                <td class="fw-semibold">{{ $item->number_ticket }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->description, 50) }}</td>
                                <td class="text-muted">{{ $item->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $item->status }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('user-help-center.show', $item->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ri-chat-3-line"></i> Balas
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada tiket. Klik "Buat Tiket" untuk memulai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $helpCenters->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <div class="modal fade" id="addHelpCenterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('user-help-center.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Buat Tiket Help Center</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="category_help_centers_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select name="category_help_centers_id" id="category_help_centers_id" class="form-select" required>
                                <option value="">— Pilih kategori —</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_help_centers_id') == $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Judul <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" class="form-control" rows="4" required>{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-1">
                            <label for="attachment" class="form-label">Lampiran (PDF/JPG, opsional)</label>
                            <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.jpg,.jpeg">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Kirim Tiket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
