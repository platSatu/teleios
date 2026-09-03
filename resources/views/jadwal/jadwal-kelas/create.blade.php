@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    @php
        // Filter list yang aktif waktu admin klik "Tambah" (lihat class
        // docblock App\Http\Controllers\Jadwal\JadwalKelasController) --
        // dipakai link "Kembali"/"Batal" supaya balik ke filter yang
        // sama, BUKAN direset. `pengajar_id`/`jadwal_mata_pelajaran_id`
        // dibaca dari FIELD form asli ($returnPengajarId/
        // $returnMataPelajaranId = query awal, sebelum admin sempat
        // mengubah dropdown), `date` dari hidden input di bawah (tidak
        // ada field 'date' asli di form ini).
        $indexParams = [
            'date' => $returnDate ?? null,
            'pengajar_id' => $returnPengajarId ?? null,
            'jadwal_mata_pelajaran_id' => $returnMataPelajaranId ?? null,
        ];
    @endphp
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ ($penggantiDariSesi ?? null) ? 'Tambah Sesi Pengganti' : 'Tambah Jadwal Kelas' }}</h4>
            <p class="text-muted mb-0">Jadwalkan satu sesi kelas — pengajar, murid, dan waktunya.</p>
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

    @if($penggantiDariSesi ?? null)
        <div class="alert alert-info">
            <i class="ri-repeat-line"></i> Sesi pengganti untuk sesi izin/sakit tanggal <strong>{{ $penggantiDariSesi->start_time?->translatedFormat('d F Y, H:i') }}</strong> ({{ $penggantiDariSesi->student?->name }}). Pilih tanggal &amp; jam pengganti di bawah -- dua pola umum: hari yang sama digabung jadi 1 jam, atau slot minggu ke-5 yang biasanya kosong.
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.kelas.store') }}" method="POST">
                        @csrf
                        @if($penggantiDariSesi ?? null)
                            <input type="hidden" name="pengganti_dari_sesi_id" value="{{ $penggantiDariSesi->id }}">
                        @endif
                        {{-- Dibawa balik lewat request()->only() di
                        JadwalKelasController::store() kalau validasi
                        gagal, supaya konteks filter Tanggal tidak hilang
                        -- lihat class docblock. Pengajar/Mata Pelajaran
                        sudah otomatis ikut karena itu FIELD form asli
                        (name="pengajar_id"/"jadwal_mata_pelajaran_id" di
                        _form.blade.php), tidak perlu hidden input
                        terpisah. --}}
                        @if($returnDate ?? null)
                            <input type="hidden" name="date" value="{{ $returnDate }}">
                        @endif
                        @include('jadwal.jadwal-kelas._form', ['kelas' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('jadwal.kelas.index', $indexParams) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
