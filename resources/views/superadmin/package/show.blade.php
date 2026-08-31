@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $package->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('package.edit', $package->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('package.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 25%">Kategori</td>
                    <td>{{ $package->categoryApplication->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Deskripsi</td>
                    <td>{{ $package->description ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Durasi</td>
                    <td>{{ $package->duration }} hari</td>
                </tr>
                <tr>
                    <td class="text-muted">Harga</td>
                    <td>Rp {{ number_format($package->price, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">User</td>
                    <td>{{ $package->user->name ?? '— Tidak terikat user —' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        <span class="badge {{ $package->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ ucfirst($package->status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Terpopuler</td>
                    <td>
                        @if ($package->is_featured)
                            <span class="badge bg-primary-subtle text-primary">Ya</span>
                        @else
                            <span class="text-muted">Tidak</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Dibuat</td>
                    <td>{{ $package->created_at->format('d M Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
@endsection
