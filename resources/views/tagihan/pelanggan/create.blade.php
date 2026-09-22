@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3">Tambah Pelanggan</h4>

                <form action="{{ route('tagihan.pelanggan.store') }}" method="POST">
                    @csrf
                    @include('tagihan.pelanggan._form')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('tagihan.pelanggan.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
