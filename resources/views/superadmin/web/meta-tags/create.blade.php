@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Meta Tag</h4>

                    <form action="{{ route('web.meta-tags.store') }}" method="POST">
                        @include('superadmin.web.meta-tags._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
