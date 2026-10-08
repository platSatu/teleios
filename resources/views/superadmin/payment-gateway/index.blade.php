@extends('layouts.dashboard')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $statusLabel = ['pending' => 'Menunggu', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan', 'active' => 'Aktif', 'suspended' => 'Dinonaktifkan'];
@endphp
<div class="row">
    <div class="col-12">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">Payment Gateway</h4>
                <p class="text-muted">Aktivasi website pemakai, tarif per metode, dan semua transaksi. Kredensial Duitku memakai Pengaturan Duitku.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="border rounded-3 p-3"><div class="text-muted small">Transaksi lunas bulan ini</div><div class="fs-4 fw-semibold">{{ number_format($summary['count'], 0, ',', '.') }}</div></div></div>
                    <div class="col-md-4"><div class="border rounded-3 p-3"><div class="text-muted small">Volume bulan ini</div><div class="fs-4 fw-semibold">{{ $rp($summary['gross']) }}</div></div></div>
                    <div class="col-md-4"><div class="border rounded-3 p-3"><div class="text-muted small">Total fee bulan ini</div><div class="fs-4 fw-semibold">{{ $rp($summary['fee']) }}</div><div class="text-muted small">sudah termasuk biaya Duitku</div></div></div>
                </div>

                <ul class="nav nav-pills gap-2 mb-3">
                    @foreach(['merchant' => 'Website Pemakai', 'tarif' => 'Tarif per Metode', 'transaksi' => 'Transaksi'] as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('superadmin.payment-gateway.index', ['tab' => $key]) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                @if($tab === 'merchant')
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light"><tr><th>Website</th><th>Company</th><th>Webhook</th><th class="text-end">Saldo</th><th>Invoice</th><th>Status &amp; Batas/menit</th></tr></thead>
                            <tbody>
                                @forelse($merchants as $m)
                                    <tr>
                                        <td class="fw-semibold">{{ $m->name }}<div class="text-muted small">{{ implode(', ', $m->allowed_domains ?? []) ?: 'belum ada domain' }}</div></td>
                                        <td>{{ $m->company->name ?? '-' }}</td>
                                        <td class="small">{{ $m->webhook_url ?: '-' }}</td>
                                        <td class="text-end">{{ $rp($m->balance) }}</td>
                                        <td>{{ $m->invoices_count }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('superadmin.payment-gateway.merchant', $m->id) }}" class="d-flex gap-2 m-0">@csrf @method('PUT')
                                                <select name="status" class="form-select form-select-sm" style="width:150px">
                                                    @foreach(['pending','active','suspended'] as $s)
                                                        <option value="{{ $s }}" @selected($m->status === $s)>{{ $statusLabel[$s] }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="rate_limit_per_minute" value="{{ $m->rate_limit_per_minute }}" min="10" class="form-control form-control-sm" style="width:100px">
                                                <button class="btn btn-sm btn-primary">Simpan</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada website yang mendaftar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $merchants->links('pagination::bootstrap-5') }}</div>
                @endif

                @if($tab === 'tarif')
                    @php $feeRows = $fees->concat([new \App\Models\PgFee(['is_active' => true, 'type' => 'va'])]); @endphp
                    <p class="text-muted small">Kode metode mengikuti kode Duitku (mis. BC = VA BCA, M2 = VA Mandiri, I1 = VA BNI, BR = VA BRI, SP = QRIS ShopeePay, NQ = QRIS Nobu, OV = OVO, DA = DANA). Pastikan metode juga aktif di akun Duitku. Fee dipotong dari dana yang masuk ke merchant.</p>
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap">
                            <thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Persen (%)</th><th>Nominal (Rp)</th><th>Urutan</th><th>Aktif</th><th></th></tr></thead>
                            <tbody>
                                @foreach($feeRows as $i => $fee)
                                    @php $fid = 'pgfee'.$i; @endphp
                                    <tr>
                                        <td><input form="{{ $fid }}" name="payment_method" value="{{ $fee->payment_method }}" class="form-control form-control-sm" style="width:80px" placeholder="BC" required></td>
                                        <td><input form="{{ $fid }}" name="name" value="{{ $fee->name }}" class="form-control form-control-sm" style="width:180px" placeholder="VA BCA" required></td>
                                        <td>
                                            <select form="{{ $fid }}" name="type" class="form-select form-select-sm">
                                                @foreach(\App\Models\PgFee::TYPES as $k => $v)<option value="{{ $k }}" @selected($fee->type === $k)>{{ $v }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td><input form="{{ $fid }}" name="fee_percent" type="number" step="0.001" min="0" value="{{ $fee->fee_percent ?? 0 }}" class="form-control form-control-sm" style="width:90px"></td>
                                        <td><input form="{{ $fid }}" name="fee_flat" type="number" step="1" min="0" value="{{ (int) ($fee->fee_flat ?? 0) }}" class="form-control form-control-sm" style="width:100px"></td>
                                        <td><input form="{{ $fid }}" name="sort_order" type="number" min="0" value="{{ $fee->sort_order ?? 0 }}" class="form-control form-control-sm" style="width:70px"></td>
                                        <td><input form="{{ $fid }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($fee->is_active)></td>
                                        <td class="d-flex gap-1">
                                            <button form="{{ $fid }}" class="btn btn-sm {{ $fee->id ? 'btn-light' : 'btn-primary' }}">{{ $fee->id ? 'Simpan' : 'Tambah' }}</button>
                                            @if($fee->id)
                                                <button form="{{ $fid }}del" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @foreach($feeRows as $i => $fee)
                        <form id="pgfee{{ $i }}" method="POST" action="{{ route('superadmin.payment-gateway.fee.save') }}">@csrf<input type="hidden" name="id" value="{{ $fee->id }}"></form>
                        @if($fee->id)
                            <form id="pgfee{{ $i }}del" method="POST" action="{{ route('superadmin.payment-gateway.fee.delete', $fee->id) }}" onsubmit="return confirm('Hapus tarif ini?');">@csrf @method('DELETE')</form>
                        @endif
                    @endforeach
                @endif

                @if($tab === 'transaksi')
                    <form method="GET" class="mb-3"><input type="hidden" name="tab" value="transaksi">
                        <select name="status" class="form-select" style="max-width:200px" onchange="this.form.submit()">
                            <option value="">Semua status</option>
                            @foreach(['pending','paid','expired','failed','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $statusLabel[$s] }}</option>@endforeach
                        </select>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light"><tr><th>Waktu</th><th>Website</th><th>External ID</th><th>Order</th><th>Metode</th><th class="text-end">Nominal</th><th class="text-end">Fee</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                    <tr>
                                        <td>{{ $inv->created_at->format('d M Y H:i') }}</td>
                                        <td>{{ $inv->merchant->name ?? '-' }}</td>
                                        <td>{{ $inv->external_id }}</td>
                                        <td class="small">{{ $inv->order_number }}</td>
                                        <td>{{ $inv->payment_name ?? '-' }}</td>
                                        <td class="text-end">{{ $rp($inv->amount) }}</td>
                                        <td class="text-end">{{ $inv->fee !== null ? $rp($inv->fee) : '-' }}</td>
                                        <td>{{ $statusLabel[$inv->status] ?? $inv->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $invoices->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
