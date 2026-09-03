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

    @if($pengajarAvailability ?? null)
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="alert alert-info">
                    <i class="ri-calendar-check-line"></i>
                    Semua ketersediaan pengajar ini (referensi):
                    @php $jadwalGroups = $pengajarAvailability->jadwalGroupedByHari(); @endphp
                    @if($jadwalGroups->isEmpty())
                        <span class="fst-italic">belum ada jadwal diisi untuk pengajar ini.</span>
                    @else
                        <ul class="mb-0 mt-1">
                            @foreach($jadwalGroups as $group)
                                <li><strong>{{ $group['label'] }}</strong>: {{ implode(', ', $group['ranges']) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($branchSettingMissing ?? false)
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="alert alert-warning">
                    Branch belum punya Jam Operasional diatur, jadi Jadwal Rutin belum bisa dibuat otomatis dari sini.
                    Atur dulu lewat menu Jadwal &gt; Branch &gt; Jam Operasional -- Student ini tetap bisa ditambahkan seperti biasa,
                    Jadwal Rutin-nya tinggal dibuat manual belakangan.
                </div>
            </div>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.student.store') }}" method="POST">
                        @csrf
                        @if($selectedKategoriId ?? null)
                            <input type="hidden" name="jadwal_kategori_id" value="{{ $selectedKategoriId }}">
                        @endif
                        @include('jadwal.jadwal-student._form', ['student' => null])

                        @if($pengajarAvailability ?? null)
                            <div class="mb-3">
                                <label class="form-label d-block">Jadwal Rutin untuk Murid Ini (opsional)</label>
                                <div class="form-text mb-2">
                                    Centang slot yang mau dipakai murid ini -- cuma slot yang MASIH KOSONG (belum dipakai murid lain) yang ditampilkan.
                                    Begitu Student disimpan, Jadwal Rutin otomatis dibuat dari slot yang dicentang, dan sesi bulan ini langsung digenerate.
                                </div>

                                @if(($openSlots ?? collect())->isEmpty())
                                    <div class="alert alert-secondary mb-0">
                                        @if($branchSettingMissing ?? false)
                                            Tidak ada slot yang bisa ditampilkan (lihat peringatan Jam Operasional di atas).
                                        @else
                                            Semua slot ketersediaan pengajar ini sudah terisi murid lain. Student tetap bisa ditambahkan --
                                            Jadwal Rutin-nya tinggal dibuat manual lewat menu "Jadwal Rutin" belakangan.
                                        @endif
                                    </div>
                                @else
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($openSlots as $slot)
                                            <div class="form-check">
                                                <input type="checkbox" name="jadwal_rutin_slot_ids[]" value="{{ $slot['id'] }}"
                                                    id="jadwal_rutin_slot_{{ $slot['id'] }}" class="form-check-input"
                                                    @checked(in_array($slot['id'], old('jadwal_rutin_slot_ids', [])))>
                                                <label for="jadwal_rutin_slot_{{ $slot['id'] }}" class="form-check-label">
                                                    {{ $slot['hari_label'] }}, {{ $slot['jam_mulai'] }} - {{ $slot['jam_selesai'] }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
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
