@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Kategori Artikel</h4>

                    <form action="{{ route('web.category-articles.store') }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.category-articles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
