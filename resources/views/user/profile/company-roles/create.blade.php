@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Add Role</h4>
                <p class="text-muted mb-0">Company, Branch, dan Unit/Divisi sudah otomatis terisi dari halaman
                    sebelumnya.</p>
            </div>
            <a href="{{ route('profile.branch-office-units.show', $unit->id) }}" class="btn btn-light">
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

                        <form action="{{ route('profile.company-roles.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="branch_office_unit_id" value="{{ $unit->id }}">

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Company</label>
                                <input type="text" class="form-control" value="{{ $company->name }}" disabled readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Branch</label>
                                <input type="text" class="form-control" value="{{ $branchOffice->name }}" disabled readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Unit/Divisi</label>
                                <input type="text" class="form-control" value="{{ $unit->name }}" disabled readonly>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Role <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name"
                                    class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                                    placeholder="Admin, Finance, Marketing, dst." required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea name="description" id="description" rows="3"
                                    class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Simpan Role</button>
                                <a href="{{ route('profile.branch-office-units.show', $unit->id) }}" class="btn btn-light">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
