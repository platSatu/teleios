@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Application Menu</h4>

                    <form action="{{ route('application-menu.store') }}" method="POST">
                        @include('superadmin.application-menu._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
