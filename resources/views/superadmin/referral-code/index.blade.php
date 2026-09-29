@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Kode Referral</h4>
                    <p class="text-muted mb-0">Kode referral tiap user (dibuat otomatis saat registrasi). Atur komisi &amp; diskon per kode, atau blokir jika perlu.</p>
                </div>
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

            {{-- Default untuk semua kode yang tidak di-override (lihat ReferralService). --}}
            <form action="{{ route('referral-code.settings.update') }}" method="POST" class="border rounded-3 p-3 mb-4">
                @csrf
                @method('PUT')
                <h6 class="mb-1">Pengaturan Referral</h6>
                <p class="text-muted small mb-3">Diskon hanya untuk pembelian pertama customer dan diambil dari komisi pemilik kode. Komisi pembelian pertama tertahan selama masa komplain, perpanjangan berikutnya langsung cair.</p>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Komisi default (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="referral_commission_percent" class="form-control" value="{{ old('referral_commission_percent', $settings['referral_commission_percent']) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Diskon customer default (Rp)</label>
                        <input type="number" step="1" min="0" name="referral_buyer_discount" class="form-control" value="{{ old('referral_buyer_discount', $settings['referral_buyer_discount']) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Batas maksimal diskon (Rp)</label>
                        <input type="number" step="1" min="0" name="referral_max_buyer_discount" class="form-control" value="{{ old('referral_max_buyer_discount', $settings['referral_max_buyer_discount']) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Masa tahan (hari)</label>
                        <input type="number" step="1" min="0" max="365" name="referral_hold_days" class="form-control" value="{{ old('referral_hold_days', $settings['referral_hold_days']) }}" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Simpan</button>
                    </div>
                </div>
            </form>

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
                            <th>Diskon Customer</th>
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
                                <td>
                                    {{ rtrim(rtrim(number_format($item->percentage ?? $settings['referral_commission_percent'], 2, '.', ''), '0'), '.') }}%
                                    @if ($item->percentage === null) <span class="text-muted small">(default)</span> @endif
                                </td>
                                <td>
                                    Rp {{ number_format(min($item->buyer_discount_amount ?? $settings['referral_buyer_discount'], $settings['referral_max_buyer_discount']), 0, ',', '.') }}
                                    @if ($item->buyer_discount_amount === null) <span class="text-muted small">(default)</span> @endif
                                </td>
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
                                <td colspan="6" class="text-center text-muted py-4">Belum ada kode referral.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                 {{ $referralCodes->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
