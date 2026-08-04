@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $user->name }}</h4>
            <p class="text-muted mb-0">{{ $user->email }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('superadmin-users.edit', $user->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('superadmin-users.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted mb-1 small">Tipe</p>
                    <span class="badge {{ $user->user_type === 'SUPERADMIN' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                        {{ $user->user_type }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted mb-1 small">Status</p>
                    <span class="badge {{ $user->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted mb-1 small">Saldo Wallet</p>
                    <h5 class="mb-0">Rp {{ number_format($user->wallet->balance ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted mb-1 small">Terdaftar</p>
                    <h6 class="mb-0">{{ $user->created_at->format('d M Y') }}</h6>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="userActivityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-deposits" type="button">
                        Deposit <span class="badge bg-secondary-subtle text-secondary">{{ $deposits->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ledger" type="button">
                        Saldo (Ledger) <span class="badge bg-secondary-subtle text-secondary">{{ $ledgerEntries->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-logins" type="button">
                        Riwayat Login <span class="badge bg-secondary-subtle text-secondary">{{ $loginHistories->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vouchers" type="button">
                        Voucher <span class="badge bg-secondary-subtle text-secondary">{{ $vouchers->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-referral" type="button">
                        Kode Referral
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ ($referralUsagesAsOwner?->total() ?? 0) + $referralUsagesAsUser->total() }}
                        </span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-voucher-promo" type="button">
                        Voucher Promo <span class="badge bg-secondary-subtle text-secondary">{{ $voucherPromoRedemptions->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-admin-actions" type="button">
                        Aksi Admin <span class="badge bg-secondary-subtle text-secondary">{{ $adminActions->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button">
                        Audit Log <span class="badge bg-secondary-subtle text-secondary">{{ $auditLogs->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-company" type="button">
                        Company <span class="badge bg-secondary-subtle text-secondary">{{ $ownedCompanies->count() + $companyMemberships->count() }}</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Deposit --}}
                <div class="tab-pane fade show active" id="tab-deposits">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Referensi</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($deposits as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->reference_number }}</td>
                                        <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge
                                                @if($item->status === 'SUCCESS') bg-success-subtle text-success
                                                @elseif($item->status === 'PENDING') bg-warning-subtle text-warning
                                                @else bg-danger-subtle text-danger
                                                @endif">
                                                {{ $item->status }}
                                            </span>
                                        </td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('deposits.show', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada deposit.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $deposits->links() }}</div>
                </div>

                {{-- Ledger --}}
                <div class="tab-pane fade" id="tab-ledger">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Sumber</th>
                                    <th>Arah</th>
                                    <th>Nominal</th>
                                    <th>Saldo Sebelum</th>
                                    <th>Saldo Sesudah</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ledgerEntries as $item)
                                    <tr>
                                        <td class="text-muted small">{{ $item->transaction->transaction_type ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $item->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ $item->direction }}
                                            </span>
                                        </td>
                                        <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($item->balance_before, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($item->balance_after, 0, ',', '.') }}</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat saldo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $ledgerEntries->links() }}</div>
                </div>

                {{-- Login history --}}
                <div class="tab-pane fade" id="tab-logins">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Login</th>
                                    <th>Logout</th>
                                    <th>Durasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($loginHistories as $item)
                                    <tr>
                                        <td>{{ optional($item->last_login)->format('d M Y H:i') ?? '-' }}</td>
                                        <td>
                                            @if ($item->last_logout)
                                                {{ $item->last_logout->format('d M Y H:i') }}
                                            @else
                                                <span class="badge bg-success-subtle text-success">Sesi aktif</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->duration !== null ? gmdate('H:i:s', $item->duration) : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Belum ada riwayat login.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $loginHistories->links() }}</div>
                </div>

                {{-- Vouchers --}}
                <div class="tab-pane fade" id="tab-vouchers">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Voucher</th>
                                    <th>Berlaku Dari</th>
                                    <th>Berlaku Sampai</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vouchers as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->kode_voucher }}</td>
                                        <td>{{ optional($item->valid_from)->format('d M Y H:i') }}</td>
                                        <td>{{ optional($item->valid_until)->format('d M Y H:i') }}</td>
                                        <td>
                                            <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada voucher.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Kode Referral: dua sisi — sebagai pemilik kode (siapa yang pakai) dan sebagai pemakai (kode siapa yang dia pakai) --}}
                <div class="tab-pane fade" id="tab-referral">
                    <div class="mb-3">
                        @if ($user->referralCode)
                            <span class="text-muted small">Kode referral milik user ini:</span>
                            <span class="fw-semibold">{{ $user->referralCode->code }}</span>
                            <span class="badge bg-secondary-subtle text-secondary">{{ $user->referralCode->percentage }}%</span>
                            <span class="badge {{ $user->referralCode->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ ucfirst($user->referralCode->status) }}
                            </span>
                        @else
                            <span class="text-muted small">User ini belum punya kode referral.</span>
                        @endif
                    </div>

                    <h6 class="mb-2">Dipakai oleh user lain (sebagai pemilik kode)</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Dipakai Oleh</th>
                                    <th>Package</th>
                                    <th>Diskon</th>
                                    <th>Komisi Didapat</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($referralUsagesAsOwner ?? []) as $item)
                                    <tr>
                                        <td>{{ $item->usedBy->name ?? '-' }} <span class="text-muted small">({{ $item->usedBy->email ?? '-' }})</span></td>
                                        <td class="text-muted small">{{ $item->subscription?->package?->name ?? '-' }}</td>
                                        <td>{{ $item->discount_percent }}%</td>
                                        <td class="fw-semibold text-success">Rp {{ number_format($item->commission_amount, 0, ',', '.') }}</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada yang memakai kode referral user ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($referralUsagesAsOwner)
                        <div class="mb-4">{{ $referralUsagesAsOwner->links() }}</div>
                    @endif

                    <h6 class="mb-2">Kode referral yang dipakai user ini (sebagai pemakai)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Pemilik Kode</th>
                                    <th>Package</th>
                                    <th>Diskon</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($referralUsagesAsUser as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->referralCode->code ?? '-' }}</td>
                                        <td>{{ $item->referralCode->user->name ?? '-' }}</td>
                                        <td class="text-muted small">{{ $item->subscription?->package?->name ?? '-' }}</td>
                                        <td>{{ $item->discount_percent }}%</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">User ini belum pernah memakai kode referral orang lain.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $referralUsagesAsUser->links() }}</div>
                </div>

                {{-- Voucher Promo (VoucherUser) redemptions oleh user ini --}}
                <div class="tab-pane fade" id="tab-voucher-promo">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Promo</th>
                                    <th>Nama Voucher</th>
                                    <th>Package</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($voucherPromoRedemptions as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->voucherUser->kode_voucher ?? '-' }}</td>
                                        <td class="text-muted small">{{ $item->voucherUser->name ?? '-' }}</td>
                                        <td class="text-muted small">{{ $item->subscription?->package?->name ?? '-' }}</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">User ini belum pernah memakai kode voucher promo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $voucherPromoRedemptions->links() }}</div>
                </div>

                {{-- Admin actions performed BY this user --}}
                <div class="tab-pane fade" id="tab-admin-actions">
                    <p class="text-muted small">Aksi tambah/kurang saldo yang dilakukan user ini terhadap wallet user lain (hanya relevan jika user ini superadmin).</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Target User</th>
                                    <th>Arah</th>
                                    <th>Nominal</th>
                                    <th>Alasan</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($adminActions as $item)
                                    <tr>
                                        <td>{{ $item->wallet->user->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $item->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ $item->direction }}
                                            </span>
                                        </td>
                                        <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                        <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->reason, 40) ?: '-' }}</td>
                                        <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada aksi admin.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $adminActions->links() }}</div>
                </div>

                {{-- Audit log --}}
                <div class="tab-pane fade" id="tab-audit">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>IP</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($auditLogs as $item)
                                    <tr>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $item->action }}</span></td>
                                        <td class="text-muted small">{{ class_basename($item->entity_type) }} — {{ \Illuminate\Support\Str::limit($item->entity_id, 8, '') }}</td>
                                        <td class="text-muted small">{{ $item->ip_address ?? '-' }}</td>
                                        <td>{{ optional($item->created_at)->format('d M Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada audit log.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $auditLogs->links() }}</div>
                </div>

                {{-- Company: dimiliki + keanggotaan --}}
                <div class="tab-pane fade" id="tab-company">
                    <h6 class="mb-2">Company Dimiliki</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Company ID</th>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ownedCompanies as $item)
                                    <tr>
                                        <td class="text-muted small">{{ $item->company_id }}</td>
                                        <td class="fw-semibold">{{ $item->name }}</td>
                                        <td>
                                            <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('company.show', $item->id) }}" class="btn btn-outline-primary">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a href="{{ route('company.edit', $item->id) }}" class="btn btn-outline-secondary">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">User ini belum punya company sendiri.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mb-2">Keanggotaan Company</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Company</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($companyMemberships as $item)
                                    <tr>
                                        <td>{{ $item->company->name ?? '-' }}</td>
                                        <td>{{ $item->role->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('company-to-user.edit', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">User ini belum jadi anggota company manapun.</td>
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
