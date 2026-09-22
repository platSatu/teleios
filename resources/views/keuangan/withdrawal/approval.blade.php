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
                <h4 class="mb-1">Persetujuan Tarik Saldo</h4>
                <p class="text-muted mb-0">Menyetujui permintaan akan langsung memicu transfer ke rekening bank tujuan melalui Duitku — masukkan PIN transaksi Anda untuk konfirmasi.</p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Menunggu Persetujuan ({{ $pending->count() }})</h5>
                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Sumber</th>
                                <th>Diajukan Oleh</th>
                                <th class="text-end">Jumlah</th>
                                <th>Bank Tujuan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending as $row)
                                <tr>
                                    <td>{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>
                                        @if($row->branch_office_id)
                                            <span class="badge bg-primary-subtle text-primary">Branch — {{ $row->branchOffice->name ?? '-' }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Pribadi — {{ $row->wallet->user->name ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->requestedBy->name ?? '-' }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                    <td>{{ $row->bank_code }} — {{ $row->bank_account }}<div class="text-muted small">{{ $row->account_name }}</div></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $row->id }}">
                                                <i class="ri-check-line"></i> Setujui
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $row->id }}">
                                                <i class="ri-close-line"></i> Tolak
                                            </button>
                                        </div>

                                        {{-- Modal Setujui (PIN) --}}
                                        <div class="modal fade" id="approveModal{{ $row->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('keuangan.withdrawal.approval.approve', $row->id) }}">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Setujui Tarik Saldo</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="mb-2">Jumlah: <strong>Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</strong></p>
                                                            <p class="mb-3">Ke: <strong>{{ $row->bank_code }} — {{ $row->bank_account }} a.n {{ $row->account_name }}</strong></p>
                                                            <label class="form-label">Masukkan PIN Transaksi (6 digit)</label>
                                                            <input type="password" name="pin" class="form-control" inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6" required autocomplete="off">
                                                            <div class="form-text text-danger">Dana akan langsung dikirim ke rekening tujuan setelah disetujui. Tindakan ini tidak bisa dibatalkan.</div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-success" onclick="return confirm('Kirim dana sekarang?');">Setujui &amp; Kirim</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Modal Tolak (alasan) --}}
                                        <div class="modal fade" id="rejectModal{{ $row->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('keuangan.withdrawal.approval.reject', $row->id) }}">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Tolak Permintaan Tarik Saldo</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <label class="form-label">Alasan Penolakan</label>
                                                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-danger">Tolak</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Tidak ada permintaan yang menunggu persetujuan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Riwayat</h5>
                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Sumber</th>
                                <th>Diajukan Oleh</th>
                                <th class="text-end">Jumlah</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($riwayat as $row)
                                <tr>
                                    <td>{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>
                                        @if($row->branch_office_id)
                                            <span class="badge bg-primary-subtle text-primary">Branch — {{ $row->branchOffice->name ?? '-' }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Pribadi — {{ $row->wallet->user->name ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->requestedBy->name ?? '-' }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
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
                                    <td class="text-muted small">
                                        {{ $row->rejection_reason ?? $row->failure_reason ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat.</td>
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
