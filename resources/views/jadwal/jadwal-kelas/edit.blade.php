@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    @php
        // Balik ke list dengan filter yang relevan dgn sesi ini APA
        // ADANYA (sebelum diedit) -- cocok dgn App\Http\Controllers\
        // Jadwal\JadwalKelasController::filterRedirectParams() yang
        // dipakai update() setelah simpan, supaya link "Kembali"/
        // "Batal" konsisten dengan ke mana admin bakal diarahkan
        // setelah submit.
        $indexParams = [
            'date' => $kelas->start_time?->toDateString() ?? '',
            'pengajar_id' => $kelas->pengajar_id ?? '',
            'jadwal_mata_pelajaran_id' => $kelas->jadwal_mata_pelajaran_id ?? '',
        ];
    @endphp
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Jadwal Kelas</h4>
            <p class="text-muted mb-0">Perbarui jadwal kelas ini.</p>
        </div>
        <a href="{{ route('jadwal.kelas.index', $indexParams) }}" class="btn btn-light">
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
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.kelas.update', $kelas->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('jadwal.jadwal-kelas._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            <a href="{{ route('jadwal.kelas.index', $indexParams) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
