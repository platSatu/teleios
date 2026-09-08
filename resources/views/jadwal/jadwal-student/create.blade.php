@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Student</h4>
            <p class="text-muted mb-0">Murid baru untuk Jadwal.</p>
        </div>
        @if($selectedGradeId ?? null)
            <a href="{{ route('jadwal.pengajar.index', ['jadwal_grade_id' => $selectedGradeId]) }}" class="btn btn-light">
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
                        @if($selectedGradeId ?? null)
                            <input type="hidden" name="jadwal_grade_id" value="{{ $selectedGradeId }}">
                        @endif
                        @include('jadwal.jadwal-student._form', ['student' => null])

                        {{--
                            Update 4 September 2026 (bug fix lanjutan,
                            lalu tampilan diganti tab -- lihat komentar
                            JadwalStudentController::create() &
                            _kategori-tabs.blade.php); update 8 September
                            2026 (fitur Grade): checklist dikelompokkan
                            per GRADE (bisa lebih dari satu kalau
                            Pengajar yang dipilih ngajar banyak Grade
                            sekaligus, sama seperti panel Edit Student),
                            ditampilkan sebagai tab (bukan ditumpuk
                            vertikal lagi) -- label tab itu sendiri yang
                            jadi nama Kategori/Grade-nya.
                        --}}
                        @if($pengajarGrades->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label d-block">Jadwal Rutin untuk Murid Ini (opsional)</label>
                                <div class="form-text mb-2">
                                    Ketersediaan Pengajar yang dipilih di atas, per Grade yang dia ajar (satu tab per Grade).
                                    Centang slot yang mau dipakai murid ini -- slot yang sudah dicoret/disabled berarti sudah
                                    dipakai murid lain. Begitu Student disimpan, Jadwal Rutin otomatis dibuat dari slot yang
                                    dicentang, sesi bulan ini langsung digenerate.
                                </div>

                                @include('jadwal.jadwal-student._kategori-tabs', [
                                    'pengajarGrades' => $pengajarGrades,
                                    'tabIdPrefix' => 'create',
                                ])
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            @if($selectedGradeId ?? null)
                                <a href="{{ route('jadwal.pengajar.index', ['jadwal_grade_id' => $selectedGradeId]) }}" class="btn btn-light">Batal</a>
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
