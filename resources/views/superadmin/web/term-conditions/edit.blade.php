@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Syarat & Ketentuan</h4>

                    <form action="{{ route('web.term-conditions.update', $term->id) }}" method="POST">
                        @include('superadmin.web.term-conditions._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
