@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Artikel Dokumentasi</h4>

                    <form action="{{ route('wa-api-dokumentasi.articles.store') }}" method="POST">
                        @include('superadmin.wa-api-dokumentasi.articles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
