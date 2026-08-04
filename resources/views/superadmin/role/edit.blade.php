@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Role</h4>

                    <form action="{{ route('roles.update', $role->id) }}" method="POST">
                        @include('superadmin.role._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
