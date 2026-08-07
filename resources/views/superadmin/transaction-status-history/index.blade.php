@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">History Deposits</h4>
                <p class="text-muted mb-0">Riwayat perubahan status (mis. PENDING &rarr; SUCCESS) — beda dengan "Data Deposits" yang menampilkan datanya sendiri.</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="entity_type" class="form-control" placeholder="Entity type (mis. Deposit)..." value="{{ request('entity_type') }}">
                </div>
                <div class="col-auto">
                    <select name="new_status" class="form-select">
                        <option value="">Semua status baru</option>
                        @foreach (['PENDING', 'SUCCESS', 'FAILED'] as $status)
                            <option value="{{ $status }}" @selected(request('new_status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Entity</th>
                            <th>Perubahan Status</th>
                            <th>Diubah Oleh</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($histories as $item)
                            <tr>
                                <td class="text-muted small">{{ class_basename($item->entity_type) }} — {{ \Illuminate\Support\Str::limit($item->entity_id, 8, '') }}</td>
                                <td>
                                    <span class="text-muted">{{ $item->old_status ?? '—' }}</span>
                                    <i class="ri-arrow-right-line mx-1"></i>
                                    <span class="fw-semibold">{{ $item->new_status }}</span>
                                </td>
                                <td>{{ $item->changer->name ?? '-' }}</td>
                                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada riwayat perubahan status.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $histories->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
