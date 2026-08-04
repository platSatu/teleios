@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Company</h4>

                    <form action="{{ route('company.update', $company->id) }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.company._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
