@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Kode Referral</h4>
                    <p class="text-muted mb-0">Kode referral tiap user (dibuat otomatis saat registrasi). Atur persentase komisi &amp; blokir jika perlu.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari kode / nama / email user..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Kode Referral</th>
                            <th>Komisi</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referralCodes as $item)
                            <tr>
                                <td>
                                    {{ $item->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                                </td>
                                <td><code>{{ $item->code }}</code></td>
                                <td>{{ rtrim(rtrim(number_format($item->percentage, 2, '.', ''), '0'), '.') }}%</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('referral-code.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        @if ($item->status === 'active')
                                            <button type="submit" form="block-referral-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Blokir kode referral ini?');">
                                                <i class="ri-forbid-line"></i> Blokir
                                            </button>
                                        @else
                                            <button type="submit" form="unblock-referral-{{ $item->id }}" class="btn btn-outline-success">
                                                <i class="ri-check-line"></i> Aktifkan
                                            </button>
                                        @endif
                                    </div>
                                    <form id="block-referral-{{ $item->id }}" action="{{ route('referral-code.block', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                    <form id="unblock-referral-{{ $item->id }}" action="{{ route('referral-code.unblock', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada kode referral.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $referralCodes->links() }}
            </div>
        </div>
    </div>
@endsection
