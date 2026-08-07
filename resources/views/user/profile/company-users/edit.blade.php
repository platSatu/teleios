@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Edit User</h4>
                <p class="text-muted mb-0">{{ $member->user->name ?? '-' }} — {{ $member->user->email ?? '-' }}</p>
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

                        <form action="{{ route('profile.company-users.update', $member->user_id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" value="{{ $member->user->name ?? '-' }}"
                                    disabled readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" value="{{ $member->user->email ?? '-' }}"
                                    disabled readonly>
                                <div class="form-text">Nama dan email tidak dapat diubah dari sini.</div>
                            </div>

                            <div class="mb-3">
                                <label for="member_handphone" class="form-label">Handphone (WhatsApp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">+62</span>
                                    <input type="text" inputmode="numeric" name="handphone" id="member_handphone"
                                        class="form-control @error('handphone') is-invalid @enderror"
                                        value="{{ old('handphone', $member->user->handphone ? substr($member->user->handphone, 2) : '') }}"
                                        placeholder="81234567890" maxlength="14">
                                    @error('handphone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Tanpa awalan 0 atau kode negara 62 — cukup 10-14 digit setelahnya. Dipakai untuk notifikasi WhatsApp otomatis (Jadwal, dll). Kosongkan untuk menghapus nomor.</div>
                            </div>

                            <div class="mb-3">
                                <label for="member_role" class="form-label">Role <span
                                        class="text-danger">*</span></label>
                                <select name="company_role_id" id="member_role"
                                    class="form-select @error('company_role_id') is-invalid @enderror" required>
                                    @foreach ($companyRoles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ old('company_role_id', $member->company_role_id) == $role->id ? 'selected' : '' }}>
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
                                        {{-- Locked to the acting admin's own branch — see
                                             create.blade.php's comment for the full
                                             rationale; enforced again server-side in
                                             CompanyUserController::update() either way. --}}
                                        <input type="text" class="form-control" value="{{ $lockedBranchOffice->name }}" disabled readonly>
                                        <input type="hidden" name="branch_office_id" id="member_branch_office" value="{{ $lockedBranchOffice->id }}">
                                        <div class="form-text">User ini tetap berada di branch Anda sendiri.</div>
                                    @else
                                        <select name="branch_office_id" id="member_branch_office"
                                            class="form-select @error('branch_office_id') is-invalid @enderror">
                                            <option value="">-- Tidak ditempatkan --</option>
                                            @foreach ($branchOffices as $branchOffice)
                                                <option value="{{ $branchOffice->id }}"
                                                    {{ old('branch_office_id', $member->branch_office_id) == $branchOffice->id ? 'selected' : '' }}>
                                                    {{ $branchOffice->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('branch_office_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
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
                                                    {{ old('branch_office_unit_id', $member->branch_office_unit_id) == $unit->id ? 'selected' : '' }}>
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
                                <div class="border rounded-3 p-2" style="max-height: 200px; overflow-y: auto;">
                                    @forelse ($categoryApplications as $category)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="category_application_id[]"
                                                id="member_category_{{ $category->id }}" value="{{ $category->id }}"
                                                {{ in_array($category->id, old('category_application_id', $memberCategoryIds)) ? 'checked' : '' }}>
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
                                    <option value="active"
                                        {{ old('status', $member->status) == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive"
                                        {{ old('status', $member->status) == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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
