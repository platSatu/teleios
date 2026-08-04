@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $article->title }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('wa-api-dokumentasi.articles.edit', $article->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('wa-api-dokumentasi.articles.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-4">
                <tr>
                    <td class="text-muted" style="width: 20%">Kategori</td>
                    <td>{{ $article->categoryDocumentation->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Endpoint</td>
                    <td><span class="badge bg-secondary-subtle text-secondary me-2">{{ $article->method }}</span><code>{{ $article->endpoint }}</code></td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        <span class="badge {{ $article->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ ucfirst($article->status) }}
                        </span>
                    </td>
                </tr>
            </table>

            @if ($article->description)
                <h6>Deskripsi</h6>
                <p>{{ $article->description }}</p>
            @endif

            @if ($article->request_example)
                <h6>Contoh Request</h6>
                <pre class="bg-light p-3 rounded"><code>{{ $article->request_example }}</code></pre>
            @endif

            @if ($article->response_example)
                <h6>Contoh Response</h6>
                <pre class="bg-light p-3 rounded"><code>{{ $article->response_example }}</code></pre>
            @endif
        </div>
    </div>
@endsection
