@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit FAQ</h4>

                    <form action="{{ route('web.faqs.update', $faq->id) }}" method="POST">
                        @include('superadmin.web.faqs._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
