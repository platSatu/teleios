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
                    Jadwal pengajar yang available:
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
                    <div class="mt-1">Sesuaikan hari &amp; jam Jadwal Rutin murid ini nanti dengan rentang di atas.</div>
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
