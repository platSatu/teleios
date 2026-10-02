@extends('layouts.dashboard')

@section('content')
    {{--
        Data Deposit superadmin (2 Oktober 2026): kartu ringkasan + 3 tab
        (Deposit, Disbursement, Penjualan Paket). Filter tanggal & user
        berlaku untuk semua kartu dan tabel; pencarian & status hanya untuk
        tabel tab aktif. Lihat Superadmin\DepositController::index() dan
        App\Services\Finance\FinanceSummaryService.
    --}}
    @php
        $tabs = ['deposit' => ['Data Deposit', 'ri-wallet-3-line'], 'disbursement' => ['Data Disbursement', 'ri-bank-line'], 'penjualan' => ['Penjualan Paket', 'ri-shopping-bag-3-line']];
        $keep = request()->only(['date_from', 'date_to', 'user_id']);
    @endphp

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Data Deposit</h4>
            <p class="text-muted mb-0">
                Ringkasan periode
                <strong>{{ $dateFrom?->translatedFormat('d M Y') ?? 'awal' }}</strong> s/d
                <strong>{{ $dateTo?->translatedFormat('d M Y') ?? 'sekarang' }}</strong>
            </p>
        </div>
    </div>

    {{-- Filter: tiap isian punya kolom sendiri, turun ke baris berikutnya di layar kecil (tidak menumpuk). --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Referensi / nama / email..." value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $tab === 'disbursement' ? \App\Models\WalletWithdrawal::STATUS_LABELS[$status] : $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">User</label>
                    <select name="user_id" class="form-select">
                        <option value="">Semua user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control @error('date_from') is-invalid @enderror" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control @error('date_to') is-invalid @enderror" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-4 col-xl-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100 text-nowrap"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'user_id', 'date_from', 'date_to']))
                        <a href="{{ route('deposits.index', ['tab' => $tab]) }}" class="btn btn-light" title="Reset filter">&times;</a>
                    @endif
                </div>
                @if ($errors->any())
                    <div class="col-12 text-danger small">{{ $errors->first() }}</div>
                @endif
            </form>
        </div>
    </div>

    @include('superadmin.deposit._stats')

    <div class="card">
        <div class="card-body">
            {{-- Tab berupa link: hanya tabel tab aktif yang dimuat. Filter tanggal & user ikut terbawa. --}}
            <div class="mb-3" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <ul class="nav nav-tabs flex-nowrap mb-0" style="width: max-content;">
                    @foreach ($tabs as $key => [$label, $icon])
                        <li class="nav-item">
                            <a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif
                                href="{{ route('deposits.index', ['tab' => $key] + $keep) }}">
                                <i class="{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                @include('superadmin.deposit._table-'.$tab)
            </div>

            <div class="mt-3">
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
