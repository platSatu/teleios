@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $categoryPackage->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('category-package.edit', $categoryPackage->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('category-package.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 25%">Deskripsi</td>
                    <td>{{ $categoryPackage->description ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">User</td>
                    <td>{{ $categoryPackage->user->name ?? '— Tidak terikat user —' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        <span class="badge {{ $categoryPackage->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ ucfirst($categoryPackage->status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Dibuat</td>
                    <td>{{ $categoryPackage->created_at->format('d M Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
@endsection
