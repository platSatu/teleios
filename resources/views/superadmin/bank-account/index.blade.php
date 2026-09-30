@extends('layouts.dashboard')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <h4 class="mb-1">Verifikasi Rekening</h4>
            <p class="text-muted mb-3">Rekening yang perlu Anda periksa sebelum bisa dipakai untuk tarik saldo.</p>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('bank-account-review.settings.update') }}" method="POST" class="border rounded-3 p-3 mb-4">
                @csrf
                @method('PUT')
                <h6 class="mb-3">Pengaturan</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small">Masa Tunggu Rekening Baru (jam)</label>
                        <input type="number" name="bank_account_hold_hours" class="form-control" min="0" max="720" value="{{ old('bank_account_hold_hours', $settings['bank_account_hold_hours']) }}" required>
                        <div class="form-text">Berapa jam sampai rekening baru bisa dipakai. Disarankan 24 jam.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Jeda Ganti Rekening (hari)</label>
                        <input type="number" name="bank_account_change_days" class="form-control" min="0" max="365" value="{{ old('bank_account_change_days', $settings['bank_account_change_days']) }}" required>
                        <div class="form-text">Berapa hari sampai user bisa ganti rekening lagi. Disarankan 30 hari.</div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 mb-4">Simpan</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pemilik Akun</th>
                            <th>Rekening Baru</th>
                            <th>Nama di Bank</th>
                            <th>Nama Terverifikasi Sebelumnya</th>
                            <th>Diajukan</th>
                            <th>Peringatan</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $account)
                            <tr>
                                <td>
                                    <a href="{{ route('bank-account-review.history', $account->user_id) }}">{{ $account->user->name ?? '-' }}</a>
                                    <div class="text-muted small">{{ $account->user->email ?? '' }}</div>
                                </td>
                                <td>{{ $account->masked() }}</td>
                                <td class="fw-semibold">{{ $account->account_name }}</td>
                                <td>{{ $account->user->verified_bank_name ?? '-' }}</td>
                                <td class="small text-muted">{{ $account->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>@include('superadmin.bank-account._flag')</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approve-{{ $account->id }}">Setujui</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject-{{ $account->id }}">Tolak</button>

                                    <div class="modal fade text-start" id="approve-{{ $account->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form class="modal-content" method="POST" action="{{ route('bank-account-review.approve', $account->id) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Setujui Rekening</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-3"><strong>{{ $account->masked() }}</strong> a.n. <strong>{{ $account->account_name }}</strong> akan langsung bisa dipakai tarik saldo oleh {{ $account->user->name ?? 'user ini' }}.</p>
                                                    <x-transaction-pin-input :id="'pin-'.$account->id" />
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-success">Setujui</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="modal fade text-start" id="reject-{{ $account->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form class="modal-content" method="POST" action="{{ route('bank-account-review.reject', $account->id) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Tolak Rekening</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label" for="reason-{{ $account->id }}">Alasan penolakan (akan dikirim ke user)</label>
                                                    <textarea name="reason" id="reason-{{ $account->id }}" class="form-control" rows="3" maxlength="500" required placeholder="Contoh: Nama pemilik rekening tidak sesuai. Silakan gunakan rekening atas nama Anda sendiri."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-danger">Tolak</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Tidak ada rekening yang perlu diperiksa. 👍</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h5 class="mb-0">Semua Rekening</h5>
                <form method="GET">
                    <div class="input-group" style="max-width: 320px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama / email user..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pemilik Akun</th>
                            <th>Rekening</th>
                            <th>Nama di Bank</th>
                            <th>Status</th>
                            <th>Didaftarkan</th>
                            <th>Peringatan</th>
                            <th class="text-end">Riwayat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $account)
                            <tr>
                                <td>
                                    {{ $account->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $account->user->email ?? '' }}</div>
                                </td>
                                <td>{{ $account->masked() }}</td>
                                <td>{{ $account->account_name }}</td>
                                <td>@include('superadmin.bank-account._state')</td>
                                <td class="small text-muted">{{ $account->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>@include('superadmin.bank-account._flag')</td>
                                <td class="text-end">
                                    <a href="{{ route('bank-account-review.history', $account->user_id) }}" class="btn btn-sm btn-light">Lihat</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada rekening terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $recent->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
@endsection
