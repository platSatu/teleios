@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Student</h4>
            <p class="text-muted mb-0">Murid baru untuk Jadwal.</p>
        </div>
        @if($selectedKategoriId ?? null)
            <a href="{{ route('jadwal.pengajar.index', ['jadwal_kategori_id' => $selectedKategoriId]) }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali ke Pengajar
            </a>
        @else
            <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $selectedMataPelajaranId, 'pengajar_id' => $selectedPengajarId])) }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        @endif
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
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.student.store') }}" method="POST">
                        @csrf
                        @if($selectedKategoriId ?? null)
                            <input type="hidden" name="jadwal_kategori_id" value="{{ $selectedKategoriId }}">
                        @endif
                        @include('jadwal.jadwal-student._form', ['student' => null])

                        {{--
                            Update 4 September 2026 (bug fix lanjutan,
                            lalu tampilan diganti tab -- lihat komentar
                            JadwalStudentController::create() &
                            _kategori-tabs.blade.php): checklist
                            dikelompokkan per Kategori (bisa lebih dari
                            satu kalau Pengajar yang dipilih ngajar
                            banyak Kategori sekaligus, sama seperti panel
                            Edit Student), ditampilkan sebagai tab
                            (bukan ditumpuk vertikal lagi) -- label tab
                            itu sendiri yang jadi nama Kategori-nya.
                        --}}
                        @if($pengajarKategoris->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label d-block">Jadwal Rutin untuk Murid Ini (opsional)</label>
                                <div class="form-text mb-2">
                                    Ketersediaan Pengajar yang dipilih di atas, per Kategori yang dia ajar (satu tab per Kategori).
                                    Centang slot yang mau dipakai murid ini -- slot yang sudah dicoret/disabled berarti sudah
                                    dipakai murid lain. Begitu Student disimpan, Jadwal Rutin otomatis dibuat dari slot yang
                                    dicentang, sesi bulan ini langsung digenerate.
                                </div>

                                @include('jadwal.jadwal-student._kategori-tabs', [
                                    'pengajarKategoris' => $pengajarKategoris,
                                    'tabIdPrefix' => 'create',
                                ])
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            @if($selectedKategoriId ?? null)
                                <a href="{{ route('jadwal.pengajar.index', ['jadwal_kategori_id' => $selectedKategoriId]) }}" class="btn btn-light">Batal</a>
                            @else
                                <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $selectedMataPelajaranId, 'pengajar_id' => $selectedPengajarId])) }}" class="btn btn-light">Batal</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
