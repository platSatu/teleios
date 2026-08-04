@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Kategori Dokumentasi</h4>

                    <form action="{{ route('wa-api-dokumentasi.categories.store') }}" method="POST">
                        @include('superadmin.wa-api-dokumentasi.categories._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
