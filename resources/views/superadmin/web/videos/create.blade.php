@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Video</h4>

                    <form action="{{ route('web.videos.store') }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.videos._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
