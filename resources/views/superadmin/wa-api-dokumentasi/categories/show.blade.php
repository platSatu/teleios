@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $category->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('wa-api-dokumentasi.categories.edit', $category->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('wa-api-dokumentasi.categories.index') }}" class="btn btn-outline-secondary btn-sm">
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
                            <td class="text-muted" style="width: 35%">Slug</td>
                            <td><code>{{ $category->slug }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Deskripsi</td>
                            <td>{{ $category->description ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">User</td>
                            <td>{{ $category->user->name ?? '— Tidak terikat user —' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ ucfirst($category->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $category->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Artikel dalam Kategori Ini</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Method</th>
                                    <th>Judul</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($category->apiDocumentations as $article)
                                    <tr>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $article->method }}</span></td>
                                        <td>
                                            <a href="{{ route('wa-api-dokumentasi.articles.show', $article->id) }}">{{ $article->title }}</a>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $article->status }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Belum ada artikel di kategori ini.</td>
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
