@extends('layouts.dashboard')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Edit Jadwal Kelas</h4>
                <form method="POST" action="{{ route('jadwal.jadwal-kelas.update', $item->id) }}">
                    @include('jadwal.jadwal-kelas._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
