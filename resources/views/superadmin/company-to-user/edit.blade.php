@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Edit Company User</h4>
                    <p class="text-muted mb-4">
                        {{ $companyToUser->user->name ?? '-' }} &middot; {{ $companyToUser->company->name ?? '-' }}
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('company-to-user.update', $companyToUser->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="company_role_id" class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="company_role_id" id="company_role_id" class="form-select" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}"
                                        @selected(old('company_role_id', $companyToUser->company_role_id) == $role->id)>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="branch_office_id" class="form-label">Branch Office</label>
                            <select name="branch_office_id" id="branch_office_id" class="form-select">
                                <option value="">-- Tidak ditempatkan --</option>
                                @foreach ($branchOffices as $branchOffice)
                                    <option value="{{ $branchOffice->id }}"
                                        @selected(old('branch_office_id', $companyToUser->branch_office_id) == $branchOffice->id)>
                                        {{ $branchOffice->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="branch_office_unit_id" class="form-label">Unit/Divisi</label>
                            <select name="branch_office_unit_id" id="branch_office_unit_id" class="form-select">
                                <option value="">-- Tidak ditempatkan --</option>
                                @foreach ($branchOfficeUnits as $unit)
                                    <option value="{{ $unit->id }}" data-branch-office="{{ $unit->branch_office_id }}"
                                        @selected(old('branch_office_unit_id', $companyToUser->branch_office_unit_id) == $unit->id)>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar unit otomatis mengikuti Branch Office yang dipilih di atas.</div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" @selected(old('status', $companyToUser->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $companyToUser->status) === 'inactive')>Inactive</option>
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
            var branchOfficeSelect = document.getElementById('branch_office_id');
            var unitSelect = document.getElementById('branch_office_unit_id');
            var allUnitOptions = Array.prototype.slice.call(unitSelect.querySelectorAll('option[data-branch-office]'));

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

            branchOfficeSelect.addEventListener('change', filterUnits);
            filterUnits();
        });
    </script>
@endsection
