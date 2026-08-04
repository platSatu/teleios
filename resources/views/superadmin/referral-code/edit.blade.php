@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Kode Referral — {{ $referralCode->user->name ?? '-' }}</h4>
        <a href="{{ route('referral-code.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-1">Kode Referral</p>
                    <h3 class="mb-3"><code>{{ $referralCode->code }}</code></h3>
                    <p class="text-muted mb-3">{{ $referralCode->user->email ?? '-' }}</p>

                    <span class="badge {{ $referralCode->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                        {{ ucfirst($referralCode->status) }}
                    </span>

                    <div class="d-flex gap-2 mt-3">
                        <form action="{{ route('referral-code.regenerate', $referralCode->id) }}" method="POST" onsubmit="return confirm('Generate ulang kode referral ini? Kode lama tidak bisa dipakai lagi.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-refresh-line"></i> Generate Ulang Kode
                            </button>
                        </form>

                        @if ($referralCode->status === 'active')
                            <form action="{{ route('referral-code.block', $referralCode->id) }}" method="POST" onsubmit="return confirm('Blokir kode referral ini?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="ri-forbid-line"></i> Blokir
                                </button>
                            </form>
                        @else
                            <form action="{{ route('referral-code.unblock', $referralCode->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm">
                                    <i class="ri-check-line"></i> Aktifkan
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Persentase Komisi</h5>
                    <form action="{{ route('referral-code.update', $referralCode->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="percentage" class="form-label">Persentase (%) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" max="100" name="percentage" id="percentage" class="form-control"
                                value="{{ old('percentage', $referralCode->percentage) }}" required>
                            <div class="form-text">Default 20%. Persentase komisi yang didapat user ini dari setiap referral.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">Riwayat Pemakaian Kode Ini</h5>
                <a href="{{ route('referral-code.usage-history') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-history-line"></i> Lihat Semua History
                </a>
            </div>

            <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
                <span><i class="ri-hand-coin-line me-1"></i> Total Komisi Diterima dari Kode Ini</span>
                <strong>Rp {{ number_format($totalCommission, 0, ',', '.') }}</strong>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-centered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dipakai Oleh</th>
                            <th>Package Dibeli</th>
                            <th>Rate</th>
                            <th>Komisi (Rp)</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $item)
                            <tr>
                                <td>
                                    {{ $item->usedBy->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->usedBy->email ?? '' }}</div>
                                </td>
                                <td>{{ $item->subscription?->package?->name ?? '-' }}</td>
                                <td>{{ rtrim(rtrim(number_format($item->discount_percent, 2, '.', ''), '0'), '.') }}%</td>
                                <td class="fw-semibold text-success">Rp {{ number_format($item->commission_amount, 0, ',', '.') }}</td>
                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Kode ini belum pernah dipakai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
