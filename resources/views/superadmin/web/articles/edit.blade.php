@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0">Edit Artikel</h4>
                        <span class="badge bg-light text-dark">Dibaca {{ number_format($article->count_read) }}x</span>
                    </div>

                    <form action="{{ route('web.articles.update', $article->id) }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.articles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
