@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">History User Login</h4>
                <p class="text-muted mb-0">Riwayat login/logout seluruh user.</p>
            </div>

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / email user..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Login</th>
                            <th>Logout</th>
                            <th>Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($histories as $item)
                            <tr>
                                <td>
                                    {{ $item->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                                </td>
                                <td>{{ optional($item->last_login)->format('d M Y H:i') ?? '-' }}</td>
                                <td>
                                    @if ($item->last_logout)
                                        {{ $item->last_logout->format('d M Y H:i') }}
                                    @else
                                        <span class="badge bg-success-subtle text-success">Sesi aktif</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->duration !== null)
                                        {{ gmdate('H:i:s', $item->duration) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada riwayat login.</td>
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
