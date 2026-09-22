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

                <p class="text-muted small mb-3">Permintaan Anda perlu disetujui admin sebelum dana benar-benar dikirim ke rekening tujuan.</p>

                <form method="POST" action="{{ route('wallet.withdrawal.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Jumlah (Rp)</label>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" min="10000" step="1" value="{{ old('amount') }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bank Tujuan</label>
                        @if(count($banks))
                            <select name="bank_code" class="form-select @error('bank_code') is-invalid @enderror" required>
                                <option value="">-- Pilih Bank --</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank['bankCode'] }}" @selected(old('bank_code') === $bank['bankCode'])>{{ $bank['bankName'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="bank_code" class="form-control @error('bank_code') is-invalid @enderror" placeholder="Kode bank (mis. 014)" value="{{ old('bank_code') }}" required>
                        @endif
                        @error('bank_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nomor Rekening</label>
                        <input type="text" name="bank_account" class="form-control @error('bank_account') is-invalid @enderror" value="{{ old('bank_account') }}" required>
                        @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Pemilik Rekening</label>
                        <input type="text" name="account_name" class="form-control @error('account_name') is-invalid @enderror" value="{{ old('account_name') }}" required>
                        @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" name="purpose" class="form-control" value="{{ old('purpose') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-send-plane-line"></i> Ajukan Tarik Saldo
                        </button>
                    </div>
                </form>
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
                                    <td class="text-end">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                    <td>{{ $row->bank_code }} — {{ $row->bank_account }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match($row->status) {
                                                'success' => 'bg-success-subtle text-success',
                                                'failed', 'rejected' => 'bg-danger-subtle text-danger',
                                                'processing', 'approved' => 'bg-info-subtle text-info',
                                                'cancelled' => 'bg-secondary-subtle text-secondary',
                                                default => 'bg-warning-subtle text-warning',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} text-capitalize">{{ str_replace('_', ' ', $row->status) }}</span>
                                        @if($row->status === 'rejected' && $row->rejection_reason)
                                            <div class="text-muted small">{{ $row->rejection_reason }}</div>
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
