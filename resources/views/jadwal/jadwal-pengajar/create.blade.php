@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Pengajar</h4>
            <p class="text-muted mb-0">
                @if($kategori)
                    Pengajar baru untuk Kategori "{{ $kategori->name }}" ({{ $mataPelajaran->name }}).
                @else
                    Pengajar baru -- pilih Kategori tujuan di bawah.
                @endif
            </p>
        </div>
        <a href="{{ route('jadwal.pengajar.index', array_filter(['jadwal_kategori_id' => $kategori->id ?? null])) }}" class="btn btn-light">
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

    @php
        // "Notifikasi jika tidak bisa" -- permintaan user untuk menu
        // Pengajar yang berdiri sendiri: kalau belum ada Kategori sama
        // sekali (mode global, dropdown Kategori bakal kosong) atau
        // belum ada Team Member yang bisa dijadikan pengajar, form
        // disembunyikan & diganti pesan yang jelas + link untuk
        // menyelesaikan prasyaratnya, daripada nampilin dropdown kosong.
        $noKategoriAvailable = ! $kategori && $kategoris->isEmpty();
        $noTeamMemberAvailable = $teamMembers->isEmpty();
        $canAdd = ! $noKategoriAvailable && ! $noTeamMemberAvailable;
    @endphp

    @if ($noKategoriAvailable)
        <div class="alert alert-warning">
            Belum ada Kategori yang bisa dipilih. Buat Kategori dulu lewat
            <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="alert-link">Mata Pelajaran / Bidang</a>
            sebelum menambahkan Pengajar.
        </div>
    @elseif ($noTeamMemberAvailable)
        <div class="alert alert-warning">
            Belum ada Team Member yang bisa dijadikan Pengajar untuk company/branch ini. Tambahkan Team Member terlebih dahulu sebelum menambahkan Pengajar.
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if ($canAdd)
                        <form action="{{ route('jadwal.pengajar.store') }}" method="POST">
                            @csrf
                            @include('jadwal.jadwal-pengajar._form', ['pengajarKategori' => null])

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                                <a href="{{ route('jadwal.pengajar.index', array_filter(['jadwal_kategori_id' => $kategori->id ?? null])) }}" class="btn btn-light">Batal</a>
                            </div>
                        </form>
                    @else
                        <a href="{{ route('jadwal.pengajar.index', array_filter(['jadwal_kategori_id' => $kategori->id ?? null])) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
