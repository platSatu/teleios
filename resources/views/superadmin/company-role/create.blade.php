@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Company Role</h4>

                    <form action="{{ route('company-role.store') }}" method="POST">
                        @include('superadmin.company-role._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
