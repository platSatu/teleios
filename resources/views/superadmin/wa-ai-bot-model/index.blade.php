@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Model AI</h4>
                    <p class="text-muted mb-0">Daftar model per provider (mis. gpt-4o di bawah OpenAI) yang muncul di dropdown Model saat company mengatur AI Bot.</p>
                </div>
                <a href="{{ route('wa-ai-bot-model.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Model
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama model..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Model</th>
                            <th>Provider</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($models as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->provider->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('wa-ai-bot-model.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-wa-ai-bot-model-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus model ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-wa-ai-bot-model-{{ $item->id }}" action="{{ route('wa-ai-bot-model.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada model AI.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $models->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
