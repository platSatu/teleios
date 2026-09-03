@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ ($penggantiDariSesi ?? null) ? 'Tambah Sesi Pengganti' : 'Tambah Jadwal Kelas' }}</h4>
            <p class="text-muted mb-0">Jadwalkan satu sesi kelas — pengajar, murid, dan waktunya.</p>
        </div>
        <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $returnRuanganId ?? ($selectedRuanganId ?? 'none'), 'date' => $returnDate ?? null]) }}" class="btn btn-light">
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
                        gagal, supaya konteks tab Ruangan + tanggal tidak
                        hilang -- lihat class docblock. --}}
                        @if($returnRuanganId ?? null)
                            <input type="hidden" name="ruangan_id" value="{{ $returnRuanganId }}">
                        @endif
                        @if($returnDate ?? null)
                            <input type="hidden" name="date" value="{{ $returnDate }}">
                        @endif
                        @include('jadwal.jadwal-kelas._form', ['kelas' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('jadwal.kelas.index', ['ruangan_id' => $returnRuanganId ?? ($selectedRuanganId ?? 'none'), 'date' => $returnDate ?? null]) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
