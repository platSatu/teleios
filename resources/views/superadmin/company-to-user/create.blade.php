@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Company User</h4>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('company-to-user.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select" required>
                                <option value="">-- Pilih Company --</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                        {{ $company->name }} ({{ $company->company_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-select" required>
                                <option value="">-- Pilih User --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="company_role_id" class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="company_role_id" id="company_role_id" class="form-select" required>
                                <option value="">-- Pilih Company dulu --</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" data-company="{{ $role->company_id }}"
                                        @selected(old('company_role_id') == $role->id)>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar role otomatis mengikuti Company yang dipilih di atas.</div>
                        </div>

                        <div class="mb-3">
                            <label for="branch_office_id" class="form-label">Branch Office</label>
                            <select name="branch_office_id" id="branch_office_id" class="form-select">
                                <option value="">-- Tidak ditempatkan --</option>
                                @foreach ($branchOffices as $branchOffice)
                                    <option value="{{ $branchOffice->id }}" data-company="{{ $branchOffice->company_id }}"
                                        @selected(old('branch_office_id') == $branchOffice->id)>
                                        {{ $branchOffice->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar branch office otomatis mengikuti Company yang dipilih di atas.</div>
                        </div>

                        <div class="mb-4">
                            <label for="branch_office_unit_id" class="form-label">Unit/Divisi</label>
                            <select name="branch_office_unit_id" id="branch_office_unit_id" class="form-select">
                                <option value="">-- Tidak ditempatkan --</option>
                                @foreach ($branchOfficeUnits as $unit)
                                    <option value="{{ $unit->id }}" data-branch-office="{{ $unit->branch_office_id }}"
                                        @selected(old('branch_office_unit_id') == $unit->id)>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar unit otomatis mengikuti Branch Office yang dipilih di atas.</div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('company-to-user.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var companySelect = document.getElementById('company_id');
            var roleSelect = document.getElementById('company_role_id');
            var allRoleOptions = Array.prototype.slice.call(roleSelect.querySelectorAll('option[data-company]'));

            var branchOfficeSelect = document.getElementById('branch_office_id');
            var allBranchOfficeOptions = Array.prototype.slice.call(branchOfficeSelect.querySelectorAll('option[data-company]'));

            var unitSelect = document.getElementById('branch_office_unit_id');
            var allUnitOptions = Array.prototype.slice.call(unitSelect.querySelectorAll('option[data-branch-office]'));

            function filterRoles() {
                var companyId = companySelect.value;

                allRoleOptions.forEach(function (opt) {
                    var matches = opt.getAttribute('data-company') === companyId;
                    opt.hidden = !matches;
                    opt.disabled = !matches;
                });

                // Reset selection if it no longer belongs to the chosen company.
                var selected = roleSelect.querySelector('option:checked');
                if (selected && selected.hasAttribute('data-company') && selected.getAttribute('data-company') !== companyId) {
                    roleSelect.value = '';
                }
            }

            function filterBranchOffices() {
                var companyId = companySelect.value;

                allBranchOfficeOptions.forEach(function (opt) {
                    var matches = opt.getAttribute('data-company') === companyId;
                    opt.hidden = !matches;
                    opt.disabled = !matches;
                });

                var selected = branchOfficeSelect.querySelector('option:checked');
                if (selected && selected.hasAttribute('data-company') && selected.getAttribute('data-company') !== companyId) {
                    branchOfficeSelect.value = '';
                }

                filterUnits();
            }

            function filterUnits() {
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
            }

            companySelect.addEventListener('change', filterRoles);
            companySelect.addEventListener('change', filterBranchOffices);
            branchOfficeSelect.addEventListener('change', filterUnits);

            filterRoles();
            filterBranchOffices();
        });
    </script>
@endsection
