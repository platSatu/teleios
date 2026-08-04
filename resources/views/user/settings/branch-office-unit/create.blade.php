@extends('layouts.dashboard')
@section('content')
    <div class="col-12">
        <div class="card card-h-100">
            <div class="card-header">
                <h5 class="card-title">Form Create</h5>
            </div>
            @include('components.notifikasi')
            <div class="card-body">
                <form action="{{ route('user-settings.branch-office-units.store') }}" method="POST">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-lg-3 d-flex align-items-center">
                            <label for="branch_office_id" class="mb-0">Branch Office</label>
                        </div>

                        <div class="col-lg-9">
                            <select class="form-select @error('branch_office_id') is-invalid @enderror"
                                id="branch_office_id" name="branch_office_id">

                                <option value="">-- Select Branch Office --</option>

                                @foreach ($branchOffices as $branchOffice)
                                    <option value="{{ $branchOffice->id }}"
                                        {{ old('branch_office_id') == $branchOffice->id ? 'selected' : '' }}>
                                        {{ $branchOffice->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('branch_office_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-lg-3 d-flex align-items-center">
                            <label for="name" class="mb-0">Name</label>
                        </div>

                        <div class="col-lg-9">
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name') }}" placeholder="Name">

                            @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-lg-3 d-flex align-items-center">
                            <label for="description" class="mb-0">Description</label>
                        </div>

                        <div class="col-lg-9">
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                id="description" name="description" rows="3" placeholder="Description">{{ old('description') }}</textarea>

                            @error('description')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-lg-3 d-flex align-items-center">
                            <label for="status" class="mb-0">Status</label>
                        </div>

                        <div class="col-lg-9">
                            <select class="form-select @error('status') is-invalid @enderror" id="status"
                                name="status">

                                <option value="">-- Select Status --</option>

                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>
                                    Active
                                </option>

                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                    Inactive
                                </option>

                            </select>

                            @error('status')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-9 offset-lg-3">
                            <button type="submit" class="btn btn-primary">
                                Submit
                            </button>

                            <a href="{{ route('user-settings.branch-office-units.index') }}" class="btn btn-light">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
