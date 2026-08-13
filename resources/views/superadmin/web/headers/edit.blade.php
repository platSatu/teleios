@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Header</h4>

                    <form action="{{ route('web.headers.update', $header->id) }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.headers._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
