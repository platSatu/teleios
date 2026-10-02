@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">Tarik Saldo</h4>
                        <p class="text-muted mb-0">Saldo saat ini: <span class="fw-semibold">Rp {{ number_format((float) ($wallet->balance ?? 0), 0, ',', '.') }}</span></p>
                    </div>
                </div>

                <p class="text-muted small mb-3">Saldo langsung ditahan saat Anda mengajukan, lalu dikirim ke rekening setelah disetujui admin. Kalau ditolak, dibatalkan, atau gagal, saldo otomatis dikembalikan.</p>

                @if (! $bankAccount)
                    <div class="alert alert-warning mb-0">
                        Anda belum punya rekening pencairan yang aktif. Tambahkan dulu supaya bisa tarik saldo.
                        <a href="{{ route('wallet.bank-account.index') }}" class="alert-link">+ Tambah Rekening</a>
                        @if ($nextBankAccount?->state() === 'scheduled')
                            <div class="small mt-1">Rekening {{ $nextBankAccount->masked() }} aktif mulai {{ $nextBankAccount->active_at->translatedFormat('d M Y, H:i') }}.</div>
                        @endif
                    </div>
                @else
                    <form method="POST" action="{{ route('wallet.withdrawal.store') }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <div class="border rounded-3 p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="text-muted small">Dikirim ke</div>
                                    <div class="fw-semibold">{{ $bankAccount->masked() }} a.n. {{ $bankAccount->account_name }}</div>
                                </div>
                                <a href="{{ route('wallet.bank-account.index') }}" class="btn btn-sm btn-outline-secondary">Ganti</a>
                            </div>
                            @if ($nextBankAccount?->state() === 'scheduled')
                                <div class="form-text">Rekening baru Anda ({{ $nextBankAccount->masked() }}) aktif mulai {{ $nextBankAccount->active_at->translatedFormat('d M Y, H:i') }}. Sampai saat itu, penarikan tetap dikirim ke rekening di atas.</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Penarikan (Rp)</label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" min="10000" step="1" value="{{ old('amount') }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @include('wallet.withdrawal._fee-preview')
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Catatan (opsional)</label>
                            <input type="text" name="purpose" class="form-control" maxlength="255" placeholder="mis. Komisi September" value="{{ old('purpose') }}">
                        </div>
                        <div class="col-md-4">
                            <x-transaction-pin-input />
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-send-plane-line"></i> Ajukan Penarikan
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Riwayat Tarik Saldo</h5>
                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">Jumlah</th>
                                <th>Bank Tujuan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($riwayat as $row)
                                <tr>
                                    <td>{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td class="text-end">
                                        Rp {{ number_format((float) $row->amount, 0, ',', '.') }}
                                        @if ((float) $row->fee_amount > 0)
                                            <div class="text-muted small">Biaya Rp {{ number_format((float) $row->fee_amount, 0, ',', '.') }} · diterima Rp {{ number_format($row->transferAmount(), 0, ',', '.') }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row->bankAccount?->masked() ?? $row->bank_code.' ****'.substr($row->bank_account, -4) }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match($row->status) {
                                                'success' => 'bg-success-subtle text-success',
                                                'failed', 'rejected' => 'bg-danger-subtle text-danger',
                                                'processing', 'approved', 'needs_review' => 'bg-info-subtle text-info',
                                                'cancelled' => 'bg-secondary-subtle text-secondary',
                                                default => 'bg-warning-subtle text-warning',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ $row->statusLabel() }}</span>
                                        @if($row->status === 'rejected' && $row->rejection_reason)
                                            <div class="text-muted small">{{ $row->rejection_reason }}</div>
                                        @endif
                                        @if($row->status === 'needs_review')
                                            <div class="text-muted small">Hasil transfer sedang dicek tim kami. Saldo masih ditahan.</div>
                                        @endif
                                        @if($row->refunded_at)
                                            <div class="text-success small">Saldo sudah dikembalikan.</div>
                                        @endif
                                        @if($row->status === 'failed' && $row->failure_reason)
                                            <div class="text-muted small">{{ $row->failure_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($row->status === 'pending_approval')
                                            <form action="{{ route('wallet.withdrawal.cancel', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Batalkan permintaan ini?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light text-danger">Batalkan</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat tarik saldo.</td>
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
