@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $voucherUser->name }} &middot; {{ $voucherUser->kode_voucher }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('voucher-user.edit', $voucherUser->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('voucher-user.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 35%">Kode Voucher</td>
                    <td class="fw-semibold">{{ $voucherUser->kode_voucher }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Persentase Diskon</td>
                    <td>{{ rtrim(rtrim(number_format($voucherUser->percentase, 2, '.', ''), '0'), '.') }}%</td>
                </tr>
                <tr>
                    <td class="text-muted">Limit Total Pemakaian</td>
                    <td>{{ $voucherUser->limit }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Batas Pemakaian per User</td>
                    <td>{{ $voucherUser->use_by_user }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Berlaku Dari</td>
                    <td>{{ optional($voucherUser->valid_from)->format('d M Y H:i') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Berlaku Sampai</td>
                    <td>{{ optional($voucherUser->valid_until)->format('d M Y H:i') }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        <span class="badge
                            @if($voucherUser->status === 'active') bg-success-subtle text-success
                            @elseif($voucherUser->status === 'inactive') bg-secondary-subtle text-secondary
                            @elseif($voucherUser->status === 'expire') bg-warning-subtle text-warning
                            @else bg-danger-subtle text-danger
                            @endif">
                            {{ ucfirst($voucherUser->status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Dibuat</td>
                    <td>{{ $voucherUser->created_at->format('d M Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
@endsection
