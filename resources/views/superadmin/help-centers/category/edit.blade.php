@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Kategori Help Center</h4>

                    <form action="{{ route('category-help-center.update', $categoryHelpCenter->id) }}" method="POST">
                        @include('superadmin.help-centers.category._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
