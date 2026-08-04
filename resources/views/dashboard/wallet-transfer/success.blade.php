@extends('layouts.dashboard')

@section('content')
    <div class="mx-auto" style="max-width: 480px;">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm text-center">
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 64px; height: 64px;">
                        <i class="ri-check-line fs-1"></i>
                    </span>
                </div>
                <h4 class="mb-1">Transfer Berhasil</h4>
                <p class="text-muted mb-4">Rp {{ number_format($transfer->amount, 0, ',', '.') }}</p>

                <table class="table table-sm table-borderless text-start mb-4">
                    <tr>
                        <td class="text-muted" style="width: 40%">
                            {{ $transfer->sender_user_id === auth()->id() ? 'Dikirim Ke' : 'Dari' }}
                        </td>
                        <td class="fw-semibold">
                            {{ $transfer->sender_user_id === auth()->id() ? ($transfer->receiver->name ?? '-') : ($transfer->sender->name ?? '-') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Catatan</td>
                        <td>{{ $transfer->note ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Saldo Anda Sebelum</td>
                        <td>
                            Rp {{ number_format($transfer->sender_user_id === auth()->id() ? $transfer->sender_balance_before : $transfer->receiver_balance_before, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Saldo Anda Sesudah</td>
                        <td class="fw-semibold">
                            Rp {{ number_format($transfer->sender_user_id === auth()->id() ? $transfer->sender_balance_after : $transfer->receiver_balance_after, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Waktu</td>
                        <td>{{ $transfer->created_at->format('d M Y H:i') }}</td>
                    </tr>
                </table>

                <div class="d-flex gap-2">
                    <a href="{{ route('dashboard.wallet-transfer.index') }}" class="btn btn-outline-secondary flex-fill">
                        Transfer Lagi
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary flex-fill">
                        Selesai
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
