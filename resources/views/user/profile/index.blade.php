@extends('layouts.dashboard')

@php
    // Which tab renders active, decided server-side so the page works
    // even without JS (no more relying on a client-side query-string
    // read to pick the visible tab). ?tab=... from the header dropdown
    // wins by default; a failed Roles/Setting Users modal submission
    // overrides it so the error lands on a tab the user can actually
    // see, and also remembers which modal to reopen.
    $activeTab = in_array(request('tab'), ['profile', 'company', 'branch-office', 'unit-divisi', 'users', 'roles', 'applications'])
        ? request('tab')
        : 'profile';

    // Branch Office / Unit-Divisi / Setting Users / Roles / Applications
    // don't exist for a company that hasn't got an active package yet —
    // same rule the controller used to decide whether to even query
    // their data. Bounce back to Company (where the "Beli Package"
    // nudge lives) instead of leaving $activeTab pointed at markup
    // that's about to render as hidden.
    if (! $hasActivePackage && in_array($activeTab, ['branch-office', 'unit-divisi', 'users', 'roles', 'applications'])) {
        $activeTab = 'company';
    }

    $autoOpenModal = null;

    if ($errors->getBag('newRole')->any()) {
        $activeTab = 'roles';
        $autoOpenModal = 'addRoleModal';
    }
    if ($errors->getBag('newRoleMenu')->any()) {
        $activeTab = 'applications';
        $autoOpenModal = 'addRoleMenuModal';
    }
    if ($errors->getBag('newBranchOffice')->any()) {
        $activeTab = 'branch-office';
        $autoOpenModal = 'addBranchOfficeModal';
    }
    if ($errors->getBag('newBranchOfficeUnit')->any()) {
        $activeTab = 'unit-divisi';
        $autoOpenModal = 'addBranchOfficeUnitModal';
    }
    // "Tambah User" / per-member "Edit" are full pages now (see
    // CompanyUserController), not modals on this page, so there's no
    // newMember/editMember{id} bag to watch for here anymore — only the
    // Import modal's `file` error still needs the auto-reopen treatment.
    if ($errors->has('file')) {
        $activeTab = 'users';
        $autoOpenModal = 'importUsersModal';
    }
    foreach ($companyRoles as $role) {
        if ($errors->getBag('editRole' . $role->id)->any()) {
            $activeTab = 'roles';
            $autoOpenModal = 'editRoleModal' . $role->id;
        }
    }
    foreach ($companyRoleMenus as $roleMenu) {
        if ($errors->getBag('editRoleMenu' . $roleMenu->id)->any()) {
            $activeTab = 'applications';
            $autoOpenModal = 'editRoleMenuModal' . $roleMenu->id;
        }
    }
    foreach ($branchOffices as $branchOffice) {
        if ($errors->getBag('editBranchOffice' . $branchOffice->id)->any()) {
            $activeTab = 'branch-office';
            $autoOpenModal = 'editBranchOfficeModal' . $branchOffice->id;
        }
    }
    foreach ($branchOfficeUnits as $branchOfficeUnit) {
        if ($errors->getBag('editBranchOfficeUnit' . $branchOfficeUnit->id)->any()) {
            $activeTab = 'unit-divisi';
            $autoOpenModal = 'editBranchOfficeUnitModal' . $branchOfficeUnit->id;
        }
    }
@endphp

@section('content')
    <div class="col-12">
        <div class="mb-4">
            <h4 class="mb-1">Profile</h4>
            <p class="text-muted mb-0">Kelola profil akun dan data company Anda.</p>
        </div>

        @include('components.notifikasi')

        @unless ($hasActivePackage)
            <div
                class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <i class="ri-lock-2-line me-1"></i>
                    Menu <strong>Branch Office</strong>, <strong>Unit/Divisi</strong>, <strong>Setting Users</strong>,
                    <strong>Roles</strong>, dan <strong>Applications</strong>
                    baru aktif setelah Anda memiliki package yang masih berlaku.
                </div>
                <a href="{{ route('dashboard.package.index') }}" class="btn btn-warning btn-sm">Lihat Package</a>
            </div>
        @endunless

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                {{-- flex-nowrap + overflow-x-auto turns this into a
                     horizontal scroller on narrow screens instead of
                     Bootstrap's default nav-tabs wrapping, which stacked
                     up to 7 tabs (Profile/Company/Branch Office/Unit-
                     Divisi/Roles/Applications/Setting Users) into a tall,
                     uneven pile on mobile. text-nowrap on each button
                     stops a label from wrapping mid-word once it's inside
                     a flex item. Same fix as resources/views/user/
                     history/index.blade.php's tab bar. --}}
                <div class="mb-4" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <ul class="nav nav-tabs flex-nowrap mb-0" id="profileTabs" role="tablist" style="width: max-content;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap {{ $activeTab === 'profile' ? 'active' : '' }}" id="tab-profile-btn"
                            data-bs-toggle="tab" data-bs-target="#tab-profile" type="button">
                            <i class="ri-user-line me-1"></i> Profile
                        </button>
                    </li>
                    @if ($isOwner)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'company' ? 'active' : '' }}" id="tab-company-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-company" type="button">
                                <i class="ri-building-line me-1"></i> Company
                            </button>
                        </li>
                    @endif
                    {{-- Each tab below now gates on its own canAccess*Tab
                         flag (see User\Profile\ProfileController::index())
                         instead of a flat $isOwner check — the owner is
                         always unrestricted; a non-owner sees it if their
                         CompanyRole was granted that route group's menu,
                         or if no superadmin has catalogued it yet
                         (fail-open, same rule as the 'menu.access'
                         middleware actually guarding these routes). --}}
                    @if ($hasActivePackage && $canAccessBranchOfficeTab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'branch-office' ? 'active' : '' }}"
                                id="tab-branch-office-btn" data-bs-toggle="tab" data-bs-target="#tab-branch-office"
                                type="button">
                                <i class="ri-community-line me-1"></i> Branch Office
                            </button>
                        </li>
                    @endif
                    @if ($hasActivePackage && $canAccessUnitDivisiTab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'unit-divisi' ? 'active' : '' }}"
                                id="tab-unit-divisi-btn" data-bs-toggle="tab" data-bs-target="#tab-unit-divisi"
                                type="button">
                                <i class="ri-organization-chart me-1"></i> Unit/Divisi
                            </button>
                        </li>
                    @endif
                    @if ($hasActivePackage && $canAccessRolesTab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'roles' ? 'active' : '' }}" id="tab-roles-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-roles" type="button">
                                <i class="ri-shield-user-line me-1"></i> Roles
                            </button>
                        </li>
                    @endif
                    @if ($hasActivePackage && $canAccessApplicationsTab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'applications' ? 'active' : '' }}"
                                id="tab-applications-btn" data-bs-toggle="tab" data-bs-target="#tab-applications"
                                type="button">
                                <i class="ri-apps-2-line me-1"></i> Applications
                            </button>
                        </li>
                    @endif
                    @if ($hasActivePackage && $canAccessUsersTab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap {{ $activeTab === 'users' ? 'active' : '' }}" id="tab-users-btn"
                                data-bs-toggle="tab" data-bs-target="#tab-users" type="button">
                                <i class="ri-group-line me-1"></i> Setting Users
                            </button>
                        </li>
                    @endif
                </ul>
                </div>

                <div class="tab-content">
                    {{-- ============================= --}}
                    {{-- TAB: PROFILE --}}
                    {{-- ============================= --}}
                    <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="tab-profile"
                        role="tabpanel">

                        {{-- Foto & Nama --}}
                        <div class="mb-4">
                            <h6 class="mb-3">Info Profil</h6>

                            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <img id="avatar-preview" src="{{ $user->avatarUrl() }}" alt="Avatar"
                                        class="rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                                    <div>
                                        <label for="image" class="btn btn-outline-secondary btn-sm mb-1">
                                            <i class="ri-camera-line"></i> Ubah Foto
                                        </label>
                                        <input type="file" name="image" id="image"
                                            accept="image/png,image/jpeg,image/webp" class="d-none">
                                        <div class="text-muted fs-12">JPG, PNG, atau WEBP. Maks 2MB.</div>
                                        @error('image')
                                            <div class="text-danger fs-12">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" class="form-control" value="{{ $user->email }}"
                                        disabled readonly>
                                    <div class="form-text">Email tidak dapat diubah.</div>
                                </div>

                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </form>
                        </div>

                        <hr class="my-4">

                        {{-- Ubah Password --}}
                        <div class="mb-4">
                            <h6 class="mb-3">Ubah Password</h6>

                            @if ($errors->updatePassword->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updatePassword->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('password.update') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Password Saat Ini <span
                                            class="text-danger">*</span></label>
                                    <input type="password" name="current_password" id="current_password"
                                        class="form-control" autocomplete="current-password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password Baru <span
                                            class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control"
                                        autocomplete="new-password" required>
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirmation" class="form-label">Ulangi Password Baru <span
                                            class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                        class="form-control" autocomplete="new-password" required>
                                </div>

                                <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
                            </form>
                        </div>

                        <hr class="my-4">

                        {{-- PIN Transaksi --}}
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h6 class="mb-1">PIN Transaksi</h6>
                                <p class="text-muted mb-0 fs-14">
                                    {{ $user->pin ? 'PIN sudah dibuat, dipakai untuk konfirmasi transfer saldo.' : 'Belum ada PIN — wajib dibuat sebelum bisa transfer saldo.' }}
                                </p>
                            </div>
                            <a href="{{ route('user-settings.pin.edit') }}" class="btn btn-outline-primary">
                                <i class="ri-shield-keyhole-line"></i> {{ $user->pin ? 'Ubah PIN' : 'Buat PIN' }}
                            </a>
                        </div>
                    </div>

                    {{-- ============================= --}}
                    {{-- TAB: COMPANY --}}
                    {{-- ============================= --}}
                    <div class="tab-pane fade {{ $activeTab === 'company' ? 'show active' : '' }}" id="tab-company"
                        role="tabpanel">

                        @if ($company)
                            <div class="row mb-4 gy-2">
                                <div class="col-sm-6">
                                    <div class="text-muted fs-12">Company ID</div>
                                    <div class="fw-semibold">{{ $company->company_id }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted fs-12">Slug</div>
                                    <div class="fw-semibold">{{ $company->slug }}</div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">
                                Anda belum memiliki data company. Lengkapi form di bawah untuk membuatnya.
                            </div>
                        @endif

                        <form action="{{ route('profile.company.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="d-flex align-items-center gap-3 mb-4">
                                <img id="company-logo-preview"
                                    src="{{ $company?->logo ? asset('storage/' . $company->logo) : asset('be') . '/assets/images/avatar/avatar-16.jpg' }}"
                                    alt="Logo" class="rounded" style="width: 72px; height: 72px; object-fit: cover;">
                                <div>
                                    <label for="logo" class="btn btn-outline-secondary btn-sm mb-1">
                                        <i class="ri-image-add-line"></i> Ubah Logo
                                    </label>
                                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp"
                                        class="d-none">
                                    <div class="text-muted fs-12">JPG, PNG, atau WEBP. Maks 2MB.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="company_name" class="form-label">Nama Company <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="company_name" id="company_name"
                                    class="form-control @error('company_name') is-invalid @enderror"
                                    value="{{ old('company_name', $company->name ?? '') }}" required>
                                @error('company_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea name="description" id="description" rows="3"
                                    class="form-control @error('description') is-invalid @enderror">{{ old('description', $company->description ?? '') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Alamat</label>
                                <input type="text" name="address" id="address"
                                    class="form-control @error('address') is-invalid @enderror"
                                    value="{{ old('address', $company->address ?? '') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="phone" class="form-label">Telepon</label>
                                    <input type="text" name="phone" id="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $company->phone ?? '') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-sm-6 mb-3">
                                    <label for="company_email" class="form-label">Email</label>
                                    <input type="email" name="email" id="company_email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $company->email ?? '') }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status"
                                    class="form-select @error('status') is-invalid @enderror">
                                    <option value="active"
                                        {{ old('status', $company->status ?? 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive"
                                        {{ old('status', $company->status ?? 'active') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">
                                {{ $company ? 'Simpan Perubahan' : 'Buat Company' }}
                            </button>
                        </form>
                    </div>

                    {{-- ============================= --}}
                    {{-- TAB: BRANCH OFFICE (requires an active package + canAccessBranchOfficeTab) --}}
                    {{-- ============================= --}}
                    @if ($hasActivePackage && $canAccessBranchOfficeTab)
                    <div class="tab-pane fade {{ $activeTab === 'branch-office' ? 'show active' : '' }}"
                        id="tab-branch-office" role="tabpanel">

                        @if (!$company)
                            <div class="alert alert-info">
                                Buat data company terlebih dahulu di tab Company sebelum menambah branch office.
                            </div>
                        @else
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addBranchOfficeModal">
                                    <i class="ri-add-line"></i> Tambah Branch Office
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="min-width: 700px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 150px;">Nama</th>
                                            <th style="min-width: 180px;">Alamat</th>
                                            <th style="min-width: 110px;">Unit/Divisi</th>
                                            <th style="min-width: 110px;">Status</th>
                                            <th style="min-width: 120px;" width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($branchOffices as $branchOffice)
                                            <tr>
                                                <td>{{ $branchOffice->name }}</td>
                                                <td>{{ $branchOffice->address ?: '-' }}</td>
                                                <td>{{ $branchOffice->units->count() }}</td>
                                                <td>
                                                    @if ($branchOffice->status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-light-primary icon-btn btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editBranchOfficeModal{{ $branchOffice->id }}"
                                                            title="Edit">
                                                            <i class="ri-edit-2-line"></i>
                                                        </button>
                                                        <form action="{{ route('profile.branch-offices.destroy', $branchOffice->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Hapus branch office ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="btn btn-light-danger icon-btn btn-sm" title="Hapus">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Belum ada branch office.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- ============================= --}}
                    {{-- TAB: UNIT/DIVISI (requires an active package + canAccessUnitDivisiTab) --}}
                    {{-- ============================= --}}
                    @if ($hasActivePackage && $canAccessUnitDivisiTab)
                    <div class="tab-pane fade {{ $activeTab === 'unit-divisi' ? 'show active' : '' }}"
                        id="tab-unit-divisi" role="tabpanel">

                        @if (!$company)
                            <div class="alert alert-info">
                                Buat data company terlebih dahulu di tab Company sebelum menambah unit/divisi.
                            </div>
                        @elseif ($branchOffices->isEmpty())
                            <div class="alert alert-info">
                                Buat branch office terlebih dahulu di tab Branch Office sebelum menambah unit/divisi.
                            </div>
                        @else
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addBranchOfficeUnitModal">
                                    <i class="ri-add-line"></i> Tambah Unit/Divisi
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="min-width: 600px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 150px;">Nama</th>
                                            <th style="min-width: 170px;">Branch Office</th>
                                            <th style="min-width: 110px;">Status</th>
                                            <th style="min-width: 120px;" width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($branchOfficeUnits as $unit)
                                            <tr>
                                                <td>{{ $unit->name }}</td>
                                                <td>{{ $unit->branchOffice->name ?? '-' }}</td>
                                                <td>
                                                    @if ($unit->status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-light-primary icon-btn btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editBranchOfficeUnitModal{{ $unit->id }}"
                                                            title="Edit">
                                                            <i class="ri-edit-2-line"></i>
                                                        </button>
                                                        <form action="{{ route('profile.branch-office-units.destroy', $unit->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Hapus unit/divisi ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="btn btn-light-danger icon-btn btn-sm" title="Hapus">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Belum ada unit/divisi.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- ============================= --}}
                    {{-- TAB: SETTING USERS (requires an active package — --}}
                    {{-- see $hasActivePackage in ProfileController) --}}
                    {{-- ============================= --}}
                    @if ($hasActivePackage && $canAccessUsersTab)
                    <div class="tab-pane fade {{ $activeTab === 'users' ? 'show active' : '' }}" id="tab-users"
                        role="tabpanel">

                        @if (!$company)
                            <div class="alert alert-info">
                                Buat data company terlebih dahulu di tab Company sebelum menambah user.
                            </div>
                        @else
                            {{-- Import result — flashed by CompanyUserController::import(). --}}
                            @if (session('importResult'))
                                @php $importResult = session('importResult'); @endphp
                                <div class="alert {{ empty($importResult['errors']) ? 'alert-success' : 'alert-warning' }}">
                                    <div class="fw-semibold mb-1">
                                        Import selesai: {{ count($importResult['created']) }} user berhasil dibuat{{ empty($importResult['errors']) ? '.' : ', ' . count($importResult['errors']) . ' baris gagal.' }}
                                    </div>

                                    @if (!empty($importResult['created']))
                                        <details class="mb-2">
                                            <summary class="small text-muted" style="cursor: pointer;">Lihat user yang
                                                berhasil dibuat</summary>
                                            <ul class="small mb-0 mt-2">
                                                @foreach ($importResult['created'] as $row)
                                                    <li>
                                                        {{ $row['name'] }} — {{ $row['email'] }}
                                                        @if ($row['password'])
                                                            <span class="text-muted">(password otomatis:
                                                                <code>{{ $row['password'] }}</code> — sampaikan ke user
                                                                terkait, tidak akan ditampilkan lagi)</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif

                                    @if (!empty($importResult['errors']))
                                        <details open>
                                            <summary class="small text-muted" style="cursor: pointer;">Lihat baris yang
                                                gagal</summary>
                                            <ul class="small mb-0 mt-2">
                                                @foreach ($importResult['errors'] as $err)
                                                    <li>
                                                        Baris {{ $err['row'] }}{{ $err['email'] ? ' (' . $err['email'] . ')' : '' }}:
                                                        {{ implode(' ', $err['messages']) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                            @endif

                            {{-- Jumlah user per Category Aplikasi --}}
                            @if ($categoryApplications->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    @foreach ($categoryApplications as $category)
                                        @php
                                            $categoryMemberCount = $companyMembers
                                                ->filter(fn ($rows) => $rows->contains(fn ($row) => $row->category_application_id === $category->id))
                                                ->count();
                                        @endphp
                                        <span class="badge bg-light text-dark border">
                                            {{ $category->name }}: <strong>{{ $categoryMemberCount }}</strong> user
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="text" id="userSearchInput" class="form-control form-control-sm"
                                        style="width: 220px;" placeholder="Cari nama / email...">
                                    <select id="userCategoryFilter" class="form-select form-select-sm"
                                        style="width: 200px;">
                                        <option value="">Semua Category Aplikasi</option>
                                        @foreach ($categoryApplications as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <select id="userStatusFilter" class="form-select form-select-sm" style="width: 150px;">
                                        <option value="">Semua Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#importUsersModal">
                                        <i class="ri-upload-2-line"></i> Import
                                    </button>
                                    <a href="{{ route('profile.company-users.export') }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-download-2-line"></i> Export
                                    </a>
                                    <a href="{{ route('profile.company-users.create') }}" class="btn btn-primary btn-sm">
                                        <i class="ri-user-add-line"></i> Tambah User
                                    </a>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="usersTable" style="min-width: 900px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 150px;">Nama</th>
                                            <th style="min-width: 180px;">Email</th>
                                            <th style="min-width: 120px;">Role</th>
                                            <th style="min-width: 180px;">Category Aplikasi</th>
                                            <th style="min-width: 110px;">Status</th>
                                            <th style="min-width: 150px;" width="150">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($companyMembers as $memberUserId => $memberRows)
                                            @php
                                                $memberFirst = $memberRows->first();
                                                $memberCategoryIds = $memberRows->pluck('category_application_id')->filter()->implode(' ');
                                                $searchHaystack = strtolower(($memberFirst->user->name ?? '') . ' ' . ($memberFirst->user->email ?? ''));
                                            @endphp
                                            <tr data-search="{{ $searchHaystack }}" data-categories="{{ $memberCategoryIds }}"
                                                data-status="{{ $memberFirst->status }}">
                                                <td>{{ $memberFirst->user->name ?? '-' }}</td>
                                                <td>{{ $memberFirst->user->email ?? '-' }}</td>
                                                <td>{{ $memberFirst->role->name ?? '-' }}</td>
                                                <td>
                                                    {{-- One user can be registered under more than 1
                                                         category application — one badge per row in
                                                         this member's group. --}}
                                                    @forelse ($memberRows->filter(fn ($row) => $row->categoryApplication) as $memberRow)
                                                        <span class="badge bg-info-subtle text-info me-1 mb-1">{{ $memberRow->categoryApplication->name }}</span>
                                                    @empty
                                                        <span class="badge bg-secondary-subtle text-secondary">Semua Akses</span>
                                                    @endforelse
                                                </td>
                                                <td>
                                                    @if ($memberFirst->status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($memberUserId === $company->user_id)
                                                        <span class="badge bg-secondary-subtle text-secondary">Owner</span>
                                                    @else
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-light-secondary icon-btn btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#showMemberModal{{ $memberUserId }}"
                                                                title="Detail">
                                                                <i class="ri-eye-line"></i>
                                                            </button>
                                                            <a href="{{ route('profile.company-users.edit', $memberUserId) }}"
                                                                class="btn btn-light-primary icon-btn btn-sm" title="Edit">
                                                                <i class="ri-edit-2-line"></i>
                                                            </a>
                                                            <form action="{{ route('profile.company-users.destroy', $memberUserId) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Keluarkan user ini dari company?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="btn btn-light-danger icon-btn btn-sm" title="Hapus">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Belum ada user.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                <div id="usersEmptyFilterState" class="text-center text-muted py-3 d-none">
                                    Tidak ada user yang cocok dengan pencarian/filter.
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- ============================= --}}
                    {{-- TAB: ROLES (requires an active package + canAccessRolesTab) --}}
                    {{-- ============================= --}}
                    @if ($hasActivePackage && $canAccessRolesTab)
                    <div class="tab-pane fade {{ $activeTab === 'roles' ? 'show active' : '' }}" id="tab-roles"
                        role="tabpanel">

                        @if (!$company)
                            <div class="alert alert-info">
                                Buat data company terlebih dahulu di tab Company sebelum menambah role.
                            </div>
                        @else
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addRoleModal">
                                    <i class="ri-add-line"></i> Tambah Role
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="min-width: 650px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 150px;">Nama Role</th>
                                            <th style="min-width: 200px;">Deskripsi</th>
                                            <th style="min-width: 110px;">Status</th>
                                            <th style="min-width: 120px;" width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($companyRoles as $role)
                                            <tr>
                                                <td>{{ $role->name }}</td>
                                                <td>{{ $role->description ?: '-' }}</td>
                                                <td>
                                                    @if ($role->status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-light-primary icon-btn btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editRoleModal{{ $role->id }}" title="Edit">
                                                            <i class="ri-edit-2-line"></i>
                                                        </button>
                                                        @if (strcasecmp($role->name, 'Owner') !== 0)
                                                            <form action="{{ route('profile.company-roles.destroy', $role->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Hapus role ini?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="btn btn-light-danger icon-btn btn-sm" title="Hapus">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Belum ada role.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- ============================= --}}
                    {{-- TAB: APPLICATIONS (requires an active package + canAccessApplicationsTab) --}}
                    {{-- ============================= --}}
                    @if ($hasActivePackage && $canAccessApplicationsTab)
                    <div class="tab-pane fade {{ $activeTab === 'applications' ? 'show active' : '' }}"
                        id="tab-applications" role="tabpanel">

                        @if (!$company)
                            <div class="alert alert-info">
                                Buat data company terlebih dahulu di tab Company sebelum menambah menu aplikasi.
                            </div>
                        @else
                            <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                                <i class="ri-information-line fs-4"></i>
                                <div>Menu di sini berlaku <strong>per Role</strong> — pilih role dulu saat menambah,
                                    supaya bisa berbeda menu antara mis. Admin dan Staff.</div>
                            </div>
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addRoleMenuModal">
                                    <i class="ri-add-line"></i> Tambah Menu
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="min-width: 800px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 130px;">Role</th>
                                            <th style="min-width: 180px;">Category Application</th>
                                            <th style="min-width: 170px;">Menu</th>
                                            <th style="min-width: 110px;">Status</th>
                                            <th style="min-width: 120px;" width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($companyRoleMenus as $roleMenu)
                                            <tr>
                                                <td>{{ $roleMenu->companyRole->name ?? '-' }}</td>
                                                <td>{{ $roleMenu->categoryApplication->name ?? '-' }}</td>
                                                <td>{{ $roleMenu->applicationMenu->name ?? '-' }}</td>
                                                <td>
                                                    @if ($roleMenu->status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-light-primary icon-btn btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editRoleMenuModal{{ $roleMenu->id }}"
                                                            title="Edit">
                                                            <i class="ri-edit-2-line"></i>
                                                        </button>
                                                        <form action="{{ route('profile.company-role-menus.destroy', $roleMenu->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Hapus menu ini dari company?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="btn btn-light-danger icon-btn btn-sm" title="Hapus">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Belum ada menu aplikasi.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($company && $hasActivePackage)
        {{-- ============================================================ --}}
        {{-- MODALS — Branch Office / Unit-Divisi. Kept OUTSIDE the --}}
        {{-- tab-content divs for the same reason as the block below --}}
        {{-- (Bootstrap modal visibility vs. an inactive .tab-pane's --}}
        {{-- display:none ancestor). Gated the same as the tabs --}}
        {{-- themselves: hidden until the company has an active package. --}}
        {{-- ============================================================ --}}

        {{-- Tambah Branch Office --}}
        <div class="modal fade" id="addBranchOfficeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('profile.branch-offices.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Branch Office</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="branchoffice_name" class="form-label">Nama <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="name" id="branchoffice_name"
                                    class="form-control @error('name', 'newBranchOffice') is-invalid @enderror"
                                    value="{{ old('name') }}" required>
                                @error('name', 'newBranchOffice')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="branchoffice_address" class="form-label">Alamat</label>
                                <input type="text" name="address" id="branchoffice_address"
                                    class="form-control @error('address', 'newBranchOffice') is-invalid @enderror"
                                    value="{{ old('address') }}">
                                @error('address', 'newBranchOffice')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="branchoffice_description" class="form-label">Deskripsi</label>
                                <textarea name="description" id="branchoffice_description" rows="2"
                                    class="form-control @error('description', 'newBranchOffice') is-invalid @enderror">{{ old('description') }}</textarea>
                                @error('description', 'newBranchOffice')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-1">
                                <label for="branchoffice_status" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select name="status" id="branchoffice_status"
                                    class="form-select @error('status', 'newBranchOffice') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status', 'newBranchOffice')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Branch Office per baris --}}
        @foreach ($branchOffices as $branchOffice)
            <div class="modal fade" id="editBranchOfficeModal{{ $branchOffice->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('profile.branch-offices.update', $branchOffice->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Branch Office: {{ $branchOffice->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name', 'editBranchOffice' . $branchOffice->id) is-invalid @enderror"
                                        value="{{ old('name', $branchOffice->name) }}" required>
                                    @error('name', 'editBranchOffice' . $branchOffice->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <input type="text" name="address"
                                        class="form-control @error('address', 'editBranchOffice' . $branchOffice->id) is-invalid @enderror"
                                        value="{{ old('address', $branchOffice->address) }}">
                                    @error('address', 'editBranchOffice' . $branchOffice->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" rows="2"
                                        class="form-control @error('description', 'editBranchOffice' . $branchOffice->id) is-invalid @enderror">{{ old('description', $branchOffice->description) }}</textarea>
                                    @error('description', 'editBranchOffice' . $branchOffice->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-1">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status"
                                        class="form-select @error('status', 'editBranchOffice' . $branchOffice->id) is-invalid @enderror">
                                        <option value="active"
                                            {{ old('status', $branchOffice->status) == 'active' ? 'selected' : '' }}>
                                            Active
                                        </option>
                                        <option value="inactive"
                                            {{ old('status', $branchOffice->status) == 'inactive' ? 'selected' : '' }}>
                                            Inactive
                                        </option>
                                    </select>
                                    @error('status', 'editBranchOffice' . $branchOffice->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Tambah Unit/Divisi --}}
        <div class="modal fade" id="addBranchOfficeUnitModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('profile.branch-office-units.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Unit/Divisi</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="unit_branch_office_id" class="form-label">Branch Office <span
                                        class="text-danger">*</span></label>
                                <select name="branch_office_id" id="unit_branch_office_id"
                                    class="form-select @error('branch_office_id', 'newBranchOfficeUnit') is-invalid @enderror"
                                    required>
                                    <option value="">-- Pilih Branch Office --</option>
                                    @foreach ($branchOffices as $branchOffice)
                                        <option value="{{ $branchOffice->id }}"
                                            {{ old('branch_office_id') == $branchOffice->id ? 'selected' : '' }}>
                                            {{ $branchOffice->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('branch_office_id', 'newBranchOfficeUnit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="unit_name" class="form-label">Nama <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="name" id="unit_name"
                                    class="form-control @error('name', 'newBranchOfficeUnit') is-invalid @enderror"
                                    value="{{ old('name') }}" required>
                                @error('name', 'newBranchOfficeUnit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="unit_description" class="form-label">Deskripsi</label>
                                <textarea name="description" id="unit_description" rows="2"
                                    class="form-control @error('description', 'newBranchOfficeUnit') is-invalid @enderror">{{ old('description') }}</textarea>
                                @error('description', 'newBranchOfficeUnit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-1">
                                <label for="unit_status" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select name="status" id="unit_status"
                                    class="form-select @error('status', 'newBranchOfficeUnit') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status', 'newBranchOfficeUnit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Unit/Divisi per baris --}}
        @foreach ($branchOfficeUnits as $unit)
            <div class="modal fade" id="editBranchOfficeUnitModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('profile.branch-office-units.update', $unit->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Unit/Divisi: {{ $unit->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Branch Office <span class="text-danger">*</span></label>
                                    <select name="branch_office_id"
                                        class="form-select @error('branch_office_id', 'editBranchOfficeUnit' . $unit->id) is-invalid @enderror"
                                        required>
                                        @foreach ($branchOffices as $branchOffice)
                                            <option value="{{ $branchOffice->id }}"
                                                {{ old('branch_office_id', $unit->branch_office_id) == $branchOffice->id ? 'selected' : '' }}>
                                                {{ $branchOffice->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_office_id', 'editBranchOfficeUnit' . $unit->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name', 'editBranchOfficeUnit' . $unit->id) is-invalid @enderror"
                                        value="{{ old('name', $unit->name) }}" required>
                                    @error('name', 'editBranchOfficeUnit' . $unit->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" rows="2"
                                        class="form-control @error('description', 'editBranchOfficeUnit' . $unit->id) is-invalid @enderror">{{ old('description', $unit->description) }}</textarea>
                                    @error('description', 'editBranchOfficeUnit' . $unit->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-1">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status"
                                        class="form-select @error('status', 'editBranchOfficeUnit' . $unit->id) is-invalid @enderror">
                                        <option value="active"
                                            {{ old('status', $unit->status) == 'active' ? 'selected' : '' }}>
                                            Active
                                        </option>
                                        <option value="inactive"
                                            {{ old('status', $unit->status) == 'inactive' ? 'selected' : '' }}>
                                            Inactive
                                        </option>
                                    </select>
                                    @error('status', 'editBranchOfficeUnit' . $unit->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @if ($company && $hasActivePackage)
        {{-- ============================================================ --}}
        {{-- MODALS — deliberately kept OUTSIDE the tab-content divs. A --}}
        {{-- Bootstrap modal toggled open via JS is still visually hidden --}}
        {{-- if a `display:none` ancestor (an inactive .tab-pane) contains --}}
        {{-- it, so these live as top-level siblings instead. Gated behind --}}
        {{-- $hasActivePackage too — every modal below belongs to the --}}
        {{-- Setting Users / Roles / Applications tabs, none of which --}}
        {{-- exist for a company without an active package. --}}
        {{-- ============================================================ --}}

        {{-- Import User dari Excel — "Tambah User" (create) and per-member
             Edit now live on their own pages (resources/views/user/profile/
             company-users/create.blade.php + edit.blade.php), not modals;
             see CompanyUserController's class docblock for why. This is
             the one remaining "Setting Users" modal, since it's small and
             genuinely benefits from staying in place with a Download
             Template link + loading state instead of a full navigation. --}}
        <div class="modal fade" id="importUsersModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('profile.company-users.import') }}" method="POST"
                        enctype="multipart/form-data" id="importUsersForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Import User dari Excel</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                Upload file <code>.xlsx</code>/<code>.xls</code>/<code>.csv</code> untuk menambah
                                banyak user sekaligus. Kolom yang dibutuhkan: <code>nama</code>, <code>email</code>,
                                <code>password</code> (boleh dikosongkan — dibuatkan otomatis), <code>role</code>,
                                <code>category_aplikasi</code> (pisahkan dengan koma jika lebih dari satu), dan
                                <code>status</code>.
                            </p>

                            <a href="{{ route('profile.company-users.import-template') }}"
                                class="btn btn-outline-secondary btn-sm mb-3">
                                <i class="ri-file-download-line"></i> Download Template
                            </a>

                            <div class="mb-1">
                                <label for="import_file" class="form-label">File <span
                                        class="text-danger">*</span></label>
                                <input type="file" name="file" id="import_file"
                                    class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv"
                                    required>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Maks 2MB, maks {{ \App\Imports\CompanyUsersImport::MAX_ROWS }} user per file.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" id="importUsersSubmitBtn">
                                <i class="ri-upload-2-line"></i> Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Detail — grouped per user_id, since one member can have several
             company_to_users rows (one per category application). --}}
        @foreach ($companyMembers as $memberUserId => $memberRows)
            @continue($memberUserId === $company->user_id)
            @php
                $memberFirst = $memberRows->first();
            @endphp

            <div class="modal fade" id="showMemberModal{{ $memberUserId }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detail User: {{ $memberFirst->user->name ?? '-' }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%">Nama</td>
                                    <td>{{ $memberFirst->user->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email</td>
                                    <td>{{ $memberFirst->user->email ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Role</td>
                                    <td>{{ $memberFirst->role->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status</td>
                                    <td>
                                        @if ($memberFirst->status === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Category Aplikasi</td>
                                    <td>
                                        @forelse ($memberRows->filter(fn ($row) => $row->categoryApplication) as $memberRow)
                                            <span class="badge bg-info-subtle text-info me-1 mb-1">{{ $memberRow->categoryApplication->name }}</span>
                                        @empty
                                            <span class="badge bg-secondary-subtle text-secondary">Semua Akses</span>
                                        @endforelse
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ditambahkan</td>
                                    <td>{{ optional($memberFirst->created_at)->format('d M Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Tambah Role --}}
        <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('profile.company-roles.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Role</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="role_name" class="form-label">Nama Role <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="name" id="role_name"
                                    class="form-control @error('name', 'newRole') is-invalid @enderror"
                                    value="{{ old('name') }}" required>
                                @error('name', 'newRole')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="role_description" class="form-label">Deskripsi</label>
                                <textarea name="description" id="role_description" rows="2"
                                    class="form-control @error('description', 'newRole') is-invalid @enderror">{{ old('description') }}</textarea>
                                @error('description', 'newRole')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-1">
                                <label for="role_status" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select name="status" id="role_status"
                                    class="form-select @error('status', 'newRole') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status', 'newRole')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Role per baris --}}
        @foreach ($companyRoles as $role)
            <div class="modal fade" id="editRoleModal{{ $role->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('profile.company-roles.update', $role->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Role: {{ $role->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama Role <span class="text-danger">*</span></label>
                                    <input type="text" name="name"
                                        class="form-control @error('name', 'editRole' . $role->id) is-invalid @enderror"
                                        value="{{ old('name', $role->name) }}" required>
                                    @error('name', 'editRole' . $role->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" rows="2"
                                        class="form-control @error('description', 'editRole' . $role->id) is-invalid @enderror">{{ old('description', $role->description) }}</textarea>
                                    @error('description', 'editRole' . $role->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-1">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status"
                                        class="form-select @error('status', 'editRole' . $role->id) is-invalid @enderror">
                                        <option value="active"
                                            {{ old('status', $role->status) == 'active' ? 'selected' : '' }}>
                                            Active
                                        </option>
                                        <option value="inactive"
                                            {{ old('status', $role->status) == 'inactive' ? 'selected' : '' }}>
                                            Inactive
                                        </option>
                                    </select>
                                    @error('status', 'editRole' . $role->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Tambah Menu Aplikasi --}}
        <div class="modal fade" id="addRoleMenuModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('profile.company-role-menus.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Menu Aplikasi</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="rolemenu_role" class="form-label">Role <span
                                        class="text-danger">*</span></label>
                                <select name="company_role_id" id="rolemenu_role"
                                    class="form-select @error('company_role_id', 'newRoleMenu') is-invalid @enderror"
                                    required>
                                    <option value="">-- Pilih Role --</option>
                                    @foreach ($companyRoles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ old('company_role_id') == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_role_id', 'newRoleMenu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Menu yang dicentang di bawah hanya berlaku untuk role ini.</div>
                            </div>

                            <div class="mb-3">
                                <label for="rolemenu_category" class="form-label">Category Application <span
                                        class="text-danger">*</span></label>
                                <select name="category_application_id" id="rolemenu_category"
                                    class="form-select @error('category_application_id', 'newRoleMenu') is-invalid @enderror"
                                    required>
                                    <option value="">-- Pilih Category Application --</option>
                                    @foreach ($categoryApplications as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('category_application_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_application_id', 'newRoleMenu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="rolemenu_menu" class="form-label">Menu <span
                                        class="text-danger">*</span></label>
                                <select name="application_menu_id" id="rolemenu_menu"
                                    class="form-select @error('application_menu_id', 'newRoleMenu') is-invalid @enderror"
                                    required>
                                    <option value="">-- Pilih Category dulu --</option>
                                    @foreach ($applicationMenus as $menu)
                                        <option value="{{ $menu->id }}" data-category="{{ $menu->category_application_id }}"
                                            {{ old('application_menu_id') == $menu->id ? 'selected' : '' }}>
                                            {{ $menu->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('application_menu_id', 'newRoleMenu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Daftar menu otomatis mengikuti Category Application yang dipilih.</div>
                            </div>

                            <div class="mb-1">
                                <label for="rolemenu_status" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select name="status" id="rolemenu_status"
                                    class="form-select @error('status', 'newRoleMenu') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status', 'newRoleMenu')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Menu Aplikasi per baris (hanya status — ganti menu berarti hapus lalu tambah baru) --}}
        @foreach ($companyRoleMenus as $roleMenu)
            <div class="modal fade" id="editRoleMenuModal{{ $roleMenu->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('profile.company-role-menus.update', $roleMenu->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Menu: {{ $roleMenu->applicationMenu->name ?? '-' }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-1">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status"
                                        class="form-select @error('status', 'editRoleMenu' . $roleMenu->id) is-invalid @enderror">
                                        <option value="active"
                                            {{ old('status', $roleMenu->status) == 'active' ? 'selected' : '' }}>
                                            Active
                                        </option>
                                        <option value="inactive"
                                            {{ old('status', $roleMenu->status) == 'inactive' ? 'selected' : '' }}>
                                            Inactive
                                        </option>
                                    </select>
                                    @error('status', 'editRoleMenu' . $roleMenu->id)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Shown from the moment the Import form is submitted until the
         redirect response replaces the page — a plain POST navigates
         away rather than staying on this page, so there's no real
         upload-progress percentage to track; this just covers the gap
         so it doesn't look like the click did nothing while the file
         uploads/is processed server-side. --}}
    <div id="importLoadingOverlay" class="d-none"
        style="position: fixed; inset: 0; background: rgba(255,255,255,.85); z-index: 2000; display: flex; align-items: center; justify-content: center; flex-direction: column;">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="fw-semibold">Memproses file, mohon tunggu...</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var avatarInput = document.getElementById('image');
            var avatarPreview = document.getElementById('avatar-preview');
            if (avatarInput) {
                avatarInput.addEventListener('change', function () {
                    if (avatarInput.files && avatarInput.files[0]) {
                        avatarPreview.src = URL.createObjectURL(avatarInput.files[0]);
                    }
                });
            }

            var logoInput = document.getElementById('logo');
            var logoPreview = document.getElementById('company-logo-preview');
            if (logoInput) {
                logoInput.addEventListener('change', function () {
                    if (logoInput.files && logoInput.files[0]) {
                        logoPreview.src = URL.createObjectURL(logoInput.files[0]);
                    }
                });
            }

            // Applications tab: filter the Menu <select> down to whatever
            // belongs to the chosen Category Application (same pattern as
            // superadmin's company-to-user create page).
            var rolemenuCategory = document.getElementById('rolemenu_category');
            var rolemenuMenu = document.getElementById('rolemenu_menu');
            if (rolemenuCategory && rolemenuMenu) {
                var allMenuOptions = Array.prototype.slice.call(rolemenuMenu.querySelectorAll('option[data-category]'));

                var filterMenus = function () {
                    var categoryId = rolemenuCategory.value;

                    allMenuOptions.forEach(function (opt) {
                        var matches = opt.getAttribute('data-category') === categoryId;
                        opt.hidden = !matches;
                        opt.disabled = !matches;
                    });

                    var selected = rolemenuMenu.querySelector('option:checked');
                    if (selected && selected.hasAttribute('data-category') && selected.getAttribute('data-category') !== categoryId) {
                        rolemenuMenu.value = '';
                    }
                };

                rolemenuCategory.addEventListener('change', filterMenus);
                filterMenus();
            }

            // Setting Users tab: real-time search (name/email) + category
            // + status filter — plain client-side row show/hide, no
            // round trip, since the whole roster is already rendered in
            // the table. Reruns on every keystroke/change.
            var userSearchInput = document.getElementById('userSearchInput');
            var userCategoryFilter = document.getElementById('userCategoryFilter');
            var userStatusFilter = document.getElementById('userStatusFilter');
            var usersTable = document.getElementById('usersTable');
            var usersEmptyFilterState = document.getElementById('usersEmptyFilterState');

            var applyUserFilters = function () {
                if (!usersTable) return;

                var search = (userSearchInput ? userSearchInput.value : '').toLowerCase().trim();
                var category = userCategoryFilter ? userCategoryFilter.value : '';
                var status = userStatusFilter ? userStatusFilter.value : '';
                var rows = usersTable.querySelectorAll('tbody tr[data-search]');
                var visibleCount = 0;

                rows.forEach(function (row) {
                    var matchesSearch = !search || row.dataset.search.indexOf(search) !== -1;
                    var matchesCategory = !category
                        || (' ' + row.dataset.categories + ' ').indexOf(' ' + category + ' ') !== -1;
                    var matchesStatus = !status || row.dataset.status === status;
                    var visible = matchesSearch && matchesCategory && matchesStatus;

                    row.classList.toggle('d-none', !visible);
                    if (visible) visibleCount++;
                });

                if (usersEmptyFilterState) {
                    usersEmptyFilterState.classList.toggle('d-none', rows.length === 0 || visibleCount !== 0);
                }
            };

            [userSearchInput, userCategoryFilter, userStatusFilter].forEach(function (el) {
                if (el) el.addEventListener('input', applyUserFilters);
            });

            // Import modal: show a loading overlay + disable the submit
            // button the moment the form is submitted. It's a plain POST
            // (not fetch/XHR), so there's no real progress percentage to
            // report — this just covers the gap between click and the
            // redirect response replacing the page, so a large file
            // doesn't look like the click did nothing.
            var importForm = document.getElementById('importUsersForm');
            var importOverlay = document.getElementById('importLoadingOverlay');
            var importSubmitBtn = document.getElementById('importUsersSubmitBtn');
            if (importForm && importOverlay) {
                importForm.addEventListener('submit', function () {
                    importOverlay.classList.remove('d-none');
                    if (importSubmitBtn) {
                        importSubmitBtn.disabled = true;
                        importSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
                    }
                });
            }

            // Which tab is active is decided server-side (see $activeTab
            // in the @php block above) — no JS needed for that anymore.
            // The only thing left to do here is reopen whichever modal
            // a failed Roles/Setting Users submission belongs to, since
            // that's a client-side-only concept (Bootstrap modal state).
            @if ($autoOpenModal)
                var modalEl = document.getElementById(@json($autoOpenModal));
                if (modalEl && window.bootstrap) {
                    new bootstrap.Modal(modalEl).show();
                }
            @endif
        });
    </script>
@endsection
