@extends('layouts.dashboard')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Tambah Mata Pelajaran</h4>
                <form method="POST" action="{{ route('jadwal.mata-pelajaran.store') }}">
                    @include('jadwal.mata-pelajaran._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
