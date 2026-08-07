@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Package</h4>
                    <p class="text-muted mb-0">Daftar paket langganan yang bisa dibeli user.</p>
                </div>
                <a href="{{ route('package.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Package
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama package..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Durasi</th>
                            <th>Harga</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($packages as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->categoryApplication->name ?? '-' }}</td>
                                <td>{{ $item->duration }} hari</td>
                                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    {{-- The delete <form> used to wrap the button, which took it out
                                         of .btn-group's direct-child selector and broke the joined
                                         look (Bootstrap only merges borders/corners for .btn-group's
                                         immediate .btn children). Button now lives directly in the
                                         group and targets the form (kept out-of-line, hidden) via the
                                         HTML5 form="" attribute instead. --}}
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('package.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-eye-line"></i> Show
                                        </a>
                                        <a href="{{ route('package.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-package-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus package ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-package-{{ $item->id }}" action="{{ route('package.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada package.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $packages->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
