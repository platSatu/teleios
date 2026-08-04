@extends('layouts.dashboard')
@section('content')
    <div class="col-12">
        <div class="card card-h-100">
            <div class="card-header">
                <h5 class="card-title">Form Create</h5>
            </div>
            @include('components.notifikasi')
            <div class="card-body">
                <form action="{{ route('company.store') }}" method="POST">
                    @csrf

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
                            <label for="status" class="mb-0">Status</label>
                        </div>

                        <div class="col-lg-9">
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">

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

                            <a href="{{ route('company.index') }}" class="btn btn-light">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
