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
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.student.update', $student->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('jadwal.jadwal-student._form')

                        {{--
                            Update 4 September 2026 (permintaan user, revisi kedua sesi yang
                            sama, lalu tampilan diganti tab): checklist slot ketersediaan
                            Pengajar yang dipilih di dropdown Pengajar DI ATAS (satu dropdown
                            saja -- lihat komentar di _form.blade.php) -- dikelompokkan per
                            Kategori karena Pengajar bisa ngajar lebih dari satu Kategori
                            (Student sendiri tidak menyimpan Kategori, lihat docblock
                            JadwalStudentController::pengajarSlotsPanel()), ditampilkan
                            sebagai tab (bukan ditumpuk vertikal lagi, lihat
                            _kategori-tabs.blade.php -- SATU sumber dipakai create.blade.php
                            juga). Yang dicentang & Simpan Perubahan diklik otomatis jadi
                            Jadwal Rutin baru untuk murid ini, sama seperti alur Tambah
                            Student -- yang di-uncheck (slot hijau "jadwal aktif murid ini")
                            dihapus dari jadwal murid ini.
                        --}}
                        @if($pengajarKategoris->isNotEmpty())
                            <div class="mb-3">
                                {{-- Update 4 September 2026 (permintaan user): "(opsional)" dihapus dari label ini. --}}
                                <label class="form-label d-block">Jadwal Rutin Murid Ini</label>
                                <div class="form-text mb-2">
                                    Ketersediaan Pengajar yang dipilih di atas, per Kategori yang dia ajar (satu tab per Kategori).
                                    Slot bertanda hijau "jadwal aktif murid ini" adalah jadwal murid ini sekarang -- centang slot
                                    lain untuk menambah, atau hilangkan centang slot hijau untuk mengganti/menghapus jadwal itu.
                                    Slot yang dicoret/disabled berarti sudah dipakai murid lain. Begitu Simpan Perubahan diklik:
                                    slot baru yang dicentang jadi Jadwal Rutin baru (sesi bulan ini langsung digenerate), dan slot
                                    hijau yang tadi di-uncheck akan DIHAPUS dari jadwal murid ini (murid & data lainnya tidak
                                    terpengaruh).
                                </div>

                                @if($branchSettingMissing)
                                    <div class="alert alert-secondary mb-0">Branch murid ini belum punya Jam Operasional diatur, jadi slot tidak bisa ditampilkan.</div>
                                @else
                                    @include('jadwal.jadwal-student._kategori-tabs', [
                                        'pengajarKategoris' => $pengajarKategoris,
                                        'tabIdPrefix' => 'edit',
                                    ])
                                @endif
                            </div>
                        @endif

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
