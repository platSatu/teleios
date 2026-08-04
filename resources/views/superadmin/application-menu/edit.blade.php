@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Application Menu</h4>

                    <form action="{{ route('application-menu.update', $applicationMenu->id) }}" method="POST">
                        @include('superadmin.application-menu._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
