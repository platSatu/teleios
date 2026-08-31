@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Student</h4>
            <p class="text-muted mb-0">Perbarui "{{ $student->name }}".</p>
        </div>
        <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id, 'pengajar_id' => $student->pengajar_id])) }}" class="btn btn-light">
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
                    <form action="{{ route('jadwal.student.update', $student->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('jadwal.jadwal-student._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id, 'pengajar_id' => $student->pengajar_id])) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
