@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Audit Logs</h4>
                <p class="text-muted mb-0">Jejak audit immutable untuk setiap aksi lewat CrudAdmin (superadmin) dan penyesuaian saldo.</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / email actor..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <input type="text" name="action" class="form-control" placeholder="Action (create/update/...)" value="{{ request('action') }}">
                </div>
                <div class="col-auto">
                    <input type="text" name="entity_type" class="form-control" placeholder="Entity type..." value="{{ request('entity_type') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>IP</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $item)
                            <tr>
                                <td>{{ $item->user->name ?? $item->actor_type ?? '-' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $item->action }}</span></td>
                                <td class="text-muted small">{{ class_basename($item->entity_type) }} — {{ \Illuminate\Support\Str::limit($item->entity_id, 8, '') }}</td>
                                <td class="text-muted small">{{ $item->ip_address ?? '-' }}</td>
                                <td>{{ optional($item->created_at)->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada audit log.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $logs->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
