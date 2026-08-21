@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Tambah User</h4>
                <p class="text-muted mb-0">Buat akun baru dan tambahkan ke company Anda.</p>
            </div>
            <a href="{{ route('profile.edit', ['tab' => 'users']) }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('profile.company-users.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="member_name" class="form-label">Nama <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="member_name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="member_email" class="form-label">Email <span
                                        class="text-danger">*</span></label>
                                <input type="email" name="email" id="member_email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" placeholder="Email untuk login karyawan" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Akun baru dibuat otomatis — user ini belum harus punya akun
                                    sebelumnya.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="member_password" class="form-label">Password <span
                                            class="text-danger">*</span></label>
                                    <input type="password" name="password" id="member_password"
                                        class="form-control @error('password') is-invalid @enderror" minlength="8"
                                        required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="member_password_confirmation" class="form-label">Konfirmasi Password
                                        <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation"
                                        id="member_password_confirmation" class="form-control" minlength="8" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="member_handphone" class="form-label">Handphone (WhatsApp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">+62</span>
                                    <input type="text" inputmode="numeric" name="handphone" id="member_handphone"
                                        class="form-control @error('handphone') is-invalid @enderror"
                                        value="{{ old('handphone') }}" placeholder="81234567890" maxlength="14">
                                    @error('handphone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Tanpa awalan 0 atau kode negara 62 — cukup 10-14 digit setelahnya. Dipakai untuk notifikasi WhatsApp otomatis.</div>
                            </div>

                            <div class="mb-3">
                                <label for="member_role" class="form-label">Role <span
                                        class="text-danger">*</span></label>
                                <select name="company_role_id" id="member_role"
                                    class="form-select @error('company_role_id') is-invalid @enderror" required>
                                    <option value="">-- Pilih Role --</option>
                                    @foreach ($companyRoles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ old('company_role_id', $prefillRoleId ?? '') == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_role_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="member_branch_office" class="form-label">Branch Office</label>
                                    @if ($lockedBranchOffice ?? null)
                                        {{-- Locked to the acting admin's own branch — no
                                             picker, just a plain confirmation + a hidden
                                             field carrying the value. Enforced again
                                             server-side regardless (see CompanyUserController::store()),
                                             this is purely so the form doesn't imply a
                                             choice that isn't actually offered. --}}
                                        <input type="text" class="form-control" value="{{ $lockedBranchOffice->name }}" disabled readonly>
                                        <input type="hidden" name="branch_office_id" id="member_branch_office" value="{{ $lockedBranchOffice->id }}">
                                        <div class="form-text">User baru otomatis ditempatkan di branch Anda sendiri.</div>
                                    @else
                                        <select name="branch_office_id" id="member_branch_office"
                                            class="form-select @error('branch_office_id') is-invalid @enderror">
                                            <option value="">-- Tidak ditempatkan --</option>
                                            @foreach ($branchOffices as $branchOffice)
                                                <option value="{{ $branchOffice->id }}"
                                                    {{ old('branch_office_id', $prefillBranchOfficeId ?? '') == $branchOffice->id ? 'selected' : '' }}>
                                                    {{ $branchOffice->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('branch_office_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if ($branchOffices->isEmpty())
                                            <div class="form-text">Belum ada branch office — buat dulu di tab Branch
                                                Office kalau perlu.</div>
                                        @endif
                                    @endif
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="member_branch_office_unit" class="form-label">Unit/Divisi</label>
                                    <select name="branch_office_unit_id" id="member_branch_office_unit"
                                        class="form-select @error('branch_office_unit_id') is-invalid @enderror">
                                        <option value="">-- Tidak ditempatkan --</option>
                                        @foreach ($branchOffices as $branchOffice)
                                            @foreach ($branchOffice->units as $unit)
                                                <option value="{{ $unit->id }}" data-branch-office="{{ $branchOffice->id }}"
                                                    {{ old('branch_office_unit_id', $prefillUnitId ?? '') == $unit->id ? 'selected' : '' }}>
                                                    {{ $unit->name }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    @error('branch_office_unit_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Daftar unit otomatis mengikuti Branch Office yang dipilih.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category Aplikasi <span class="text-danger">*</span></label>
                                {{-- Checkboxes, not a single select — one user can be
                                     registered under more than 1 category application. --}}
                                <div class="border rounded-3 p-2" style="max-height: 200px; overflow-y: auto;">
                                    @forelse ($categoryApplications as $category)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="category_application_id[]"
                                                id="member_category_{{ $category->id }}" value="{{ $category->id }}"
                                                {{ in_array($category->id, old('category_application_id', array_filter([$prefillCategoryId ?? null]))) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="member_category_{{ $category->id }}">
                                                {{ $category->name }}
                                            </label>
                                        </div>
                                    @empty
                                        <span class="text-muted small">Belum ada category aplikasi yang tersedia.</span>
                                    @endforelse
                                </div>
                                @error('category_application_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="member_status" class="form-label">Status <span
                                        class="text-danger">*</span></label>
                                <select name="status" id="member_status"
                                    class="form-select @error('status') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Tambah User</button>
                                <a href="{{ route('profile.edit', ['tab' => 'users']) }}" class="btn btn-light">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Filter the Unit/Divisi <select> down to whatever belongs to
            // the chosen Branch Office — same pattern as the Applications
            // tab's Category/Menu filter on the main Profile page.
            var branchOfficeSelect = document.getElementById('member_branch_office');
            var unitSelect = document.getElementById('member_branch_office_unit');
            if (branchOfficeSelect && unitSelect) {
                var allUnitOptions = Array.prototype.slice.call(unitSelect.querySelectorAll('option[data-branch-office]'));

                var filterUnits = function () {
                    var branchOfficeId = branchOfficeSelect.value;

                    allUnitOptions.forEach(function (opt) {
                        var matches = opt.getAttribute('data-branch-office') === branchOfficeId;
                        opt.hidden = !matches;
                        opt.disabled = !matches;
                    });

                    var selected = unitSelect.querySelector('option:checked');
                    if (selected && selected.hasAttribute('data-branch-office') && selected.getAttribute('data-branch-office') !== branchOfficeId) {
                        unitSelect.value = '';
                    }
                };

                branchOfficeSelect.addEventListener('change', filterUnits);
                filterUnits();
            }
        });
    </script>
@endsection
