@extends('layouts.dashboard')

@section('content')
    {{-- Kartu ringkasan: mengikuti filter tanggal, user, dan pencarian di
         bawah (bukan filter status), lihat Superadmin\DepositController::index(). --}}
    @if ($dateFrom || $dateTo)
        <p class="text-muted small mb-2">
            Ringkasan periode
            <strong>{{ $dateFrom?->translatedFormat('d M Y') ?? 'awal' }}</strong> s/d
            <strong>{{ $dateTo?->translatedFormat('d M Y') ?? 'sekarang' }}</strong>
        </p>
    @endif
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm mb-0 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-item avatar-lg rounded-circle bg-primary-subtle text-primary flex-shrink-0">
                        <i class="ri-file-list-3-line fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Deposit</div>
                        <h4 class="mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm mb-0 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-item avatar-lg rounded-circle bg-success-subtle text-success flex-shrink-0">
                        <i class="ri-wallet-3-line fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Nominal Sukses</div>
                        <h4 class="mb-0">Rp {{ number_format($stats['success_amount'], 0, ',', '.') }}</h4>
                        <div class="text-muted small">{{ number_format($stats['success'], 0, ',', '.') }} transaksi sukses</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm mb-0 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-item avatar-lg rounded-circle bg-warning-subtle text-warning flex-shrink-0">
                        <i class="ri-time-line fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending</div>
                        <h4 class="mb-0">{{ number_format($stats['pending'], 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm mb-0 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="avatar-item avatar-lg rounded-circle bg-danger-subtle text-danger flex-shrink-0">
                        <i class="ri-error-warning-line fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Gagal / Kedaluwarsa</div>
                        <h4 class="mb-0">{{ number_format($stats['failed'] + $stats['expired'], 0, ',', '.') }}</h4>
                        <div class="text-muted small">Failed {{ number_format($stats['failed'], 0, ',', '.') }} · Expired {{ number_format($stats['expired'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Deposit</h4>
                    <p class="text-muted mb-0">Data deposit seluruh user.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari referensi / nama / email..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="user_id" class="form-select">
                        <option value="">Semua user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <div class="input-group">
                        <span class="input-group-text">Dari</span>
                        <input type="date" name="date_from" class="form-control @error('date_from') is-invalid @enderror" value="{{ request('date_from') }}">
                        <span class="input-group-text">s/d</span>
                        <input type="date" name="date_to" class="form-control @error('date_to') is-invalid @enderror" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'user_id', 'date_from', 'date_to']))
                        <a href="{{ route('deposits.index') }}" class="btn btn-light" title="Reset filter">Reset</a>
                    @endif
                </div>
                @if ($errors->has('date_from') || $errors->has('date_to'))
                    <div class="col-12 text-danger small">{{ $errors->first('date_to') ?: $errors->first('date_from') }}</div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>User</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deposits as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->reference_number }}</td>
                                <td>
                                    {{ $item->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                                </td>
                                <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                <td>{{ $item->payment_method ?? '-' }}</td>
                                <td>
                                    <span class="badge
                                        @if($item->status === 'SUCCESS') bg-success-subtle text-success
                                        @elseif($item->status === 'PENDING') bg-warning-subtle text-warning
                                        @else bg-danger-subtle text-danger
                                        @endif">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('deposits.show', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-eye-line"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada deposit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $deposits->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
