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
                <h4 class="mb-1">Tarik Saldo Branch</h4>
                <p class="text-muted mb-3">Tarik sisa saldo Branch (setelah fee pengajar) ke rekening bank Company.</p>

                @if($branches->count() > 1)
                    <form method="GET" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label mb-1">Branch</label>
                            <select name="branch_office_id" class="form-select" onchange="this.form.submit()">
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branch && $branch->id === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                @endif

                @if($branch && $wallet)
                    <p class="mb-3">Saldo {{ $branch->name }} saat ini: <span class="fw-semibold">Rp {{ number_format((float) $wallet->balance, 0, ',', '.') }}</span></p>

                    <form method="POST" action="{{ route('keuangan.withdrawal.store') }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="branch_office_id" value="{{ $branch->id }}">
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
                            <label class="form-label">Nomor Rekening Company</label>
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
                @endif
            </div>
        </div>

        @if($branch)
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Tarik Saldo — {{ $branch->name }}</h5>
                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Jumlah</th>
                                    <th>Bank Tujuan</th>
                                    <th>Status</th>
                                    <th>Diajukan Oleh</th>
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
                                        </td>
                                        <td>{{ $row->requestedBy->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat tarik saldo untuk branch ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
