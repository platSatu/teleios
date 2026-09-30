@extends('layouts.dashboard')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h4 class="mb-1">Riwayat Rekening, {{ $user->name }}</h4>
                    <p class="text-muted mb-0">{{ $user->email }}</p>
                </div>
                <a href="{{ route('bank-account-review.index') }}" class="btn btn-sm btn-outline-secondary">Kembali</a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="border rounded-3 p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <div class="text-muted small">Nama Terverifikasi</div>
                    <div class="fw-semibold">{{ $user->verified_bank_name ?? 'Belum ada (rekening berikutnya menjadi patokan)' }}</div>
                </div>
                @if ($user->verified_bank_name)
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reset-name">Reset Nama</button>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p class="text-muted small mb-3">Setiap kali nomor lengkap dibuka akan tercatat atas nama Anda.</p>
            <div class="table-responsive">
                <table class="table table-centered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Rekening</th>
                            <th>Nama di Bank</th>
                            <th>Status</th>
                            <th>Pemeriksaan</th>
                            <th>Perangkat</th>
                            <th>Peringatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr>
                                <td class="small text-nowrap">{{ $account->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-nowrap">
                                    {{ $account->bank_name }}
                                    <span data-reveal-target="{{ $account->id }}">****{{ $account->account_last4 }}</span>
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-reveal="{{ route('bank-account-review.reveal', $account->id) }}" data-reveal-id="{{ $account->id }}" title="Lihat nomor lengkap">👁</button>
                                </td>
                                <td>
                                    {{ $account->account_name }}
                                    <div class="small {{ $account->name_matched ? 'text-success' : 'text-warning' }}">{{ $account->name_matched ? '✅ Nama cocok' : '⚠️ Nama berbeda' }}</div>
                                </td>
                                <td>
                                    @include('superadmin.bank-account._state')
                                    @if ($account->active_at)
                                        <div class="small text-muted">Aktif {{ $account->active_at->translatedFormat('d M Y H:i') }}</div>
                                    @endif
                                    @if ($account->replaced_at)
                                        <div class="small text-muted">Diganti {{ $account->replaced_at->translatedFormat('d M Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($account->reviewed_at)
                                        oleh {{ $account->reviewer->name ?? '-' }}, {{ $account->reviewed_at->translatedFormat('d M Y H:i') }}
                                        @if ($account->review_note)
                                            <div class="text-muted">"{{ $account->review_note }}"</div>
                                        @endif
                                    @else
                                        <span class="text-muted">Otomatis</span>
                                    @endif
                                </td>
                                <td class="small text-muted" style="max-width: 220px;">
                                    IP {{ $account->ip_address ?? '-' }}
                                    <div class="text-truncate" title="{{ $account->user_agent }}">{{ $account->user_agent }}</div>
                                </td>
                                <td>@include('superadmin.bank-account._flag')</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">User ini belum pernah mendaftarkan rekening.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($resets->isNotEmpty())
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Riwayat Reset Nama</h6>
                <ul class="list-unstyled mb-0 small">
                    @foreach ($resets as $log)
                        <li class="mb-2">
                            <strong>{{ $log->created_at->translatedFormat('d M Y H:i') }}</strong> · oleh {{ $log->user->name ?? '-' }} ·
                            nama sebelumnya <strong>{{ $log->old_value['verified_bank_name'] ?? '-' }}</strong> ·
                            "{{ $log->new_value['reason'] ?? '' }}"
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($user->verified_bank_name)
        <div class="modal fade" id="reset-name" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('bank-account-review.reset-name', $user->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reset nama terverifikasi?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Rekening berikutnya yang didaftarkan user ini akan menjadi patokan nama baru. Gunakan hanya untuk kasus khusus, misalnya user pernah salah mendaftarkan rekening.</p>
                        <label class="form-label" for="reset-reason">Alasan</label>
                        <textarea name="reason" id="reset-reason" class="form-control" rows="3" maxlength="500" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Reset</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.querySelectorAll('[data-reveal]').forEach(function (button) {
            button.addEventListener('click', function () {
                fetch(button.dataset.reveal, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                })
                    .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                    .then(function (data) {
                        document.querySelector('[data-reveal-target="' + button.dataset.revealId + '"]').textContent = data.number;
                        button.remove();
                    })
                    .catch(function () { button.title = 'Gagal memuat nomor'; });
            });
        });
    </script>
@endsection
