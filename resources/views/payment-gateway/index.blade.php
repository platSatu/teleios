@extends('layouts.dashboard')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $statusBadge = [
        'pending' => 'bg-warning-subtle text-warning', 'paid' => 'bg-success-subtle text-success',
        'expired' => 'bg-secondary-subtle text-secondary', 'failed' => 'bg-danger-subtle text-danger',
        'cancelled' => 'bg-secondary-subtle text-secondary', 'active' => 'bg-success-subtle text-success',
        'suspended' => 'bg-danger-subtle text-danger',
    ];
    $statusLabel = ['pending' => 'Menunggu', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan', 'active' => 'Aktif', 'suspended' => 'Dinonaktifkan'];
    $tabs = ['ringkasan' => 'Ringkasan', 'transaksi' => 'Transaksi', 'saldo' => 'Saldo', 'pengaturan' => 'Pengaturan', 'dokumentasi' => 'Dokumentasi'];
@endphp
<div class="row">
    <div class="col-12">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if(session('pg_secret'))
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1"><i class="ri-key-2-line"></i> Secret Key (hanya ditampilkan sekali)</div>
                <code class="d-block text-break user-select-all">{{ session('pg_secret') }}</code>
                <div class="small mt-1">Simpan di server website Anda (mis. file .env). Jangan pernah taruh di JavaScript/browser.</div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">Payment Gateway</h4>
                        <p class="text-muted mb-0">Terima pembayaran VA, QRIS, dan e-wallet di website Anda sendiri lewat teleios.</p>
                    </div>
                    @if($merchant)
                        <span class="badge {{ $statusBadge[$merchant->status] ?? 'bg-light text-dark' }} fs-6">{{ $statusLabel[$merchant->status] ?? $merchant->status }}</span>
                    @endif
                </div>

                @if(! $merchant)
                    <div class="alert alert-info">Belum ada akun Payment Gateway. {{ $isOwner ? 'Isi data website Anda di bawah untuk membuat API Key.' : 'Minta owner company untuk membuat akun.' }}</div>
                    @php $tab = 'pengaturan'; @endphp
                @else
                    <ul class="nav nav-pills gap-2 mb-4 flex-nowrap overflow-auto">
                        @foreach($tabs as $key => $label)
                            <li class="nav-item"><a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" href="{{ route('payment-gateway.index', ['tab' => $key]) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                    @if($merchant->status === 'pending')
                        <div class="alert alert-warning">Akun menunggu aktivasi dari tim teleios. Selama belum aktif, API akan menolak request.</div>
                    @endif
                @endif

                @if($tab === 'ringkasan' && $merchant)
                    <div class="row g-3">
                        <div class="col-6 col-lg-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Saldo</div><div class="fs-4 fw-semibold">{{ $rp($merchant->balance) }}</div><div class="text-muted small">siap ditarik</div></div></div>
                        <div class="col-6 col-lg-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Lunas bulan ini</div><div class="fs-4 fw-semibold">{{ number_format($summary['count'], 0, ',', '.') }}</div><div class="text-muted small">transaksi</div></div></div>
                        <div class="col-6 col-lg-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Dana masuk bulan ini</div><div class="fs-5 fw-semibold">{{ $rp($summary['net']) }}</div><div class="text-muted small">dari {{ $rp($summary['gross']) }} (setelah fee)</div></div></div>
                        <div class="col-6 col-lg-3"><div class="border rounded-3 p-3 h-100"><div class="text-muted small">Menunggu dibayar</div><div class="fs-4 fw-semibold">{{ number_format($summary['pending'], 0, ',', '.') }}</div><div class="text-muted small">invoice aktif</div></div></div>
                    </div>
                    <h6 class="mt-4">Tarif per metode</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($fees as $f)
                            <span class="badge bg-light text-dark border fw-normal">{{ $f->name }} &middot; {{ $f->label() }}</span>
                        @empty
                            <span class="text-muted small">Belum ada metode aktif.</span>
                        @endforelse
                    </div>
                    <p class="text-muted small mt-2 mb-0">Fee dipotong dari dana yang masuk. Pembeli membayar sesuai nominal invoice.</p>
                @endif

                @if($tab === 'transaksi' && $merchant)
                    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                        <input type="hidden" name="tab" value="transaksi">
                        <select name="status" class="form-select" style="max-width:180px" onchange="this.form.submit()">
                            <option value="">Semua status</option>
                            @foreach(['pending','paid','expired','failed','cancelled'] as $s)
                                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $statusLabel[$s] }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" style="max-width:260px" placeholder="Cari external ID / nama pembeli">
                        <button class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light"><tr><th>Waktu</th><th>External ID</th><th>Pembeli</th><th>Metode</th><th class="text-end">Nominal</th><th class="text-end">Fee</th><th class="text-end">Bersih</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                    <tr>
                                        <td>{{ $inv->created_at->format('d M Y H:i') }}</td>
                                        <td class="fw-semibold">{{ $inv->external_id }}</td>
                                        <td>{{ $inv->customer_name }}</td>
                                        <td>{{ $inv->payment_name ?? '-' }}</td>
                                        <td class="text-end">{{ $rp($inv->amount) }}</td>
                                        <td class="text-end">{{ $inv->fee !== null ? $rp($inv->fee) : '-' }}</td>
                                        <td class="text-end">{{ $inv->net_amount !== null ? $rp($inv->net_amount) : '-' }}</td>
                                        <td><span class="badge {{ $statusBadge[$inv->status] ?? '' }}">{{ $statusLabel[$inv->status] ?? $inv->status }}</span></td>
                                        <td>
                                            @if($merchant->webhook_url && $inv->status !== 'pending')
                                                <form method="POST" action="{{ route('payment-gateway.webhook.resend', $inv->id) }}" class="m-0">@csrf
                                                    <button class="btn btn-sm btn-light" title="Kirim ulang webhook"><i class="ri-send-plane-line"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
                @endif

                @if($tab === 'saldo' && $merchant)
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div><div class="text-muted small">Saldo saat ini</div><div class="fs-3 fw-semibold">{{ $rp($merchant->balance) }}</div></div>
                        <span class="text-muted small">Tarik Saldo ke rekening segera hadir di menu ini.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light"><tr><th>Waktu</th><th>Keterangan</th><th class="text-end">Masuk/Keluar</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @forelse($ledger as $row)
                                    <tr>
                                        <td>{{ $row->created_at?->format('d M Y H:i') }}</td>
                                        <td>{{ $row->description }}</td>
                                        <td class="text-end {{ $row->type === 'credit' ? 'text-success' : 'text-danger' }}">{{ $row->type === 'credit' ? '+' : '-' }}{{ $rp($row->amount) }}</td>
                                        <td class="text-end">{{ $rp($row->balance_after) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada mutasi saldo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $ledger->links('pagination::bootstrap-5') }}</div>
                @endif

                @if($tab === 'pengaturan')
                    @if($merchant)
                        <div class="mb-4">
                            <label class="form-label">API Key (publik)</label>
                            <input class="form-control font-monospace" value="{{ $merchant->api_key }}" readonly onclick="this.select()">
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                <span class="text-muted small">Secret Key tidak bisa ditampilkan lagi.</span>
                                @if($isOwner)
                                    <form method="POST" action="{{ route('payment-gateway.secret') }}" class="m-0" onsubmit="return confirm('Buat Secret baru? Secret lama langsung tidak berlaku dan website Anda harus diperbarui.');">@csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="ri-refresh-line"></i> Buat Secret Baru</button>
                                    </form>
                                @endif
                            </div>
                            <div class="text-muted small mt-1">Batas: {{ number_format($merchant->rate_limit_per_minute, 0, ',', '.') }} request/menit.</div>
                        </div>
                    @endif

                    @if($isOwner)
                        <form method="POST" action="{{ route('payment-gateway.save') }}" style="max-width:640px">@csrf
                            <div class="mb-3">
                                <label class="form-label">Nama website <span class="text-danger">*</span></label>
                                <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $merchant->name ?? '') }}" placeholder="Mis. INA Group" required>
                                <div class="form-text">Tampil di popup pembayaran dan nama Virtual Account.</div>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL logo (https)</label>
                                <input name="logo_url" class="form-control @error('logo_url') is-invalid @enderror" value="{{ old('logo_url', $merchant->logo_url ?? '') }}" placeholder="https://website-anda.com/logo.png">
                                @error('logo_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Webhook URL (https)</label>
                                <input name="webhook_url" class="form-control @error('webhook_url') is-invalid @enderror" value="{{ old('webhook_url', $merchant->webhook_url ?? '') }}" placeholder="https://website-anda.com/webhook/teleios">
                                <div class="form-text">teleios mengirim status pembayaran (lunas/kedaluwarsa/gagal) ke alamat ini.</div>
                                @error('webhook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Domain yang boleh menampilkan popup</label>
                                <textarea name="allowed_domains" rows="2" class="form-control" placeholder="inagroup.co.id">{{ old('allowed_domains', implode("\n", $merchant->allowed_domains ?? [])) }}</textarea>
                                <div class="form-text">Satu domain per baris, subdomain otomatis ikut. Popup tidak akan tampil di domain lain.</div>
                            </div>
                            <button class="btn btn-primary">{{ $merchant ? 'Simpan Pengaturan' : 'Buat Akun & API Key' }}</button>
                        </form>
                    @elseif($merchant)
                        <p class="text-muted mb-0">Pengaturan hanya bisa diubah oleh owner company.</p>
                    @endif
                @endif

                @if($tab === 'dokumentasi' && $merchant)
                    @include('payment-gateway._docs')
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
