@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Kategori Aplikasi</h4>

                    <form action="{{ route('category-application.store') }}" method="POST">
                        @include('superadmin.category-application._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
