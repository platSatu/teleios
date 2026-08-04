@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $voucher->kode_voucher }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('voucher.edit', $voucher->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('voucher.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 35%">User</td>
                            <td>{{ $voucher->user->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Berlaku Dari</td>
                            <td>{{ optional($voucher->valid_from)->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Berlaku Sampai</td>
                            <td>{{ optional($voucher->valid_until)->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <span class="badge
                                    @if($voucher->status === 'active') bg-success-subtle text-success
                                    @elseif($voucher->status === 'pending') bg-warning-subtle text-warning
                                    @else bg-danger-subtle text-danger
                                    @endif">
                                    {{ ucfirst($voucher->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $voucher->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Perubahan Voucher Ini</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Aksi</th>
                                    <th>Dilakukan Oleh</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($histories as $item)
                                    <tr>
                                        <td>
                                            <span class="badge
                                                @if($item->action === 'CREATE') bg-success-subtle text-success
                                                @elseif($item->action === 'UPDATE') bg-warning-subtle text-warning
                                                @else bg-danger-subtle text-danger
                                                @endif">
                                                {{ $item->action }}
                                            </span>
                                        </td>
                                        <td>{{ $item->actionBy->name ?? '-' }}</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Belum ada riwayat perubahan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
