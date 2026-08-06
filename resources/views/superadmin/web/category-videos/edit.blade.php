@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Kategori Video</h4>

                    <form action="{{ route('web.category-videos.update', $categoryVideo->id) }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.category-videos._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
