@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $categoryApplication->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('category-application.edit', $categoryApplication->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('category-application.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 35%">Deskripsi</td>
                            <td>{{ $categoryApplication->description ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">User</td>
                            <td>{{ $categoryApplication->user->name ?? '— Tidak terikat user —' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <span class="badge {{ $categoryApplication->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ ucfirst($categoryApplication->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $categoryApplication->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Package dalam Kategori Ini</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Durasi</th>
                                    <th>Harga</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categoryApplication->packages as $package)
                                    <tr>
                                        <td class="fw-semibold">{{ $package->name }}</td>
                                        <td>{{ $package->duration }} hari</td>
                                        <td>Rp {{ number_format($package->price, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge {{ $package->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($package->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Belum ada package di kategori ini.</td>
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
