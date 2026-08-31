@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Student</h4>
            <p class="text-muted mb-0">Murid baru untuk Jadwal.</p>
        </div>
        <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $selectedMataPelajaranId, 'pengajar_id' => $selectedPengajarId])) }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.student.store') }}" method="POST">
                        @csrf
                        @include('jadwal.jadwal-student._form', ['student' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $selectedMataPelajaranId, 'pengajar_id' => $selectedPengajarId])) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
