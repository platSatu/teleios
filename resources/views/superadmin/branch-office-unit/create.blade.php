@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Unit/Divisi</h4>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('branch-office-unit.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select">
                                <option value="">-- Pilih Company --</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                        {{ $company->name }} ({{ $company->company_id }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Hanya untuk memfilter pilihan Branch Office di bawah — tidak
                                disimpan langsung (unit terhubung lewat branch_office_id).</div>
                        </div>

                        <div class="mb-3">
                            <label for="branch_office_id" class="form-label">Branch Office <span class="text-danger">*</span></label>
                            <select name="branch_office_id" id="branch_office_id" class="form-select" required>
                                <option value="">-- Pilih Company dulu --</option>
                                @foreach ($branchOffices as $branchOffice)
                                    <option value="{{ $branchOffice->id }}" data-company="{{ $branchOffice->company_id }}"
                                        @selected(old('branch_office_id') == $branchOffice->id)>
                                        {{ $branchOffice->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar branch office otomatis mengikuti Company yang dipilih di atas.</div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
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
                            <a href="{{ route('branch-office-unit.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var companySelect = document.getElementById('company_id');
            var branchOfficeSelect = document.getElementById('branch_office_id');
            var allBranchOfficeOptions = Array.prototype.slice.call(branchOfficeSelect.querySelectorAll('option[data-company]'));

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
            }

            // Pre-select the Company filter on load if a branch office was
            // already chosen (e.g. redisplay after a validation error).
            var preselected = branchOfficeSelect.querySelector('option:checked[data-company]');
            if (preselected && !companySelect.value) {
                companySelect.value = preselected.getAttribute('data-company');
            }

            companySelect.addEventListener('change', filterBranchOffices);
            filterBranchOffices();
        });
    </script>
@endsection
