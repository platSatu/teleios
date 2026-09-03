@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    @php
        // Mode grid asal (lihat class docblock App\Http\Controllers\
        // Jadwal\JadwalKelasController, update 7 September 2026) --
        // dipakai link "Kembali"/"Batal" & hidden input `group_by` di
        // form supaya update() balik ke tab yang sama setelah simpan.
        $editDate = $kelas->start_time?->toDateString() ?? now()->toDateString();
        $indexParams = ($groupByReturn ?? 'ruangan') === 'pengajar'
            ? ['group_by' => 'pengajar', 'pengajar_id' => $kelas->pengajar_id, 'date' => $editDate]
            : ['ruangan_id' => $kelas->jadwal_ruangan_id ?: 'none', 'date' => $editDate];
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
                        <input type="hidden" name="group_by" value="{{ $groupByReturn ?? 'ruangan' }}">
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
