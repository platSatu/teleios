@extends('layouts.dashboard')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $muridName = $sesiMurid->jadwalKelasMurid->murid->name ?? '-';
            $originalKelas = $sesiMurid->sesi->jadwalKelas;
            $label = $originalKelas->name ?: $originalKelas->mataPelajaran->name ?? '-';
        @endphp

        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">Cari Jadwal Pengganti</h4>
                <p class="text-muted mb-3">
                    Murid: <strong>{{ $muridName }}</strong> &middot;
                    Kelas asal: {{ $label }} &middot;
                    Tanggal asal: {{ \Illuminate\Support\Carbon::parse($sesiMurid->sesi->tanggal)->translatedFormat('l, d M Y') }}
                </p>

                @if ($slots->isEmpty())
                    <div class="alert alert-secondary">
                        Sistem tidak menemukan jadwal kelas lain untuk mata pelajaran yang sama dengan slot tersedia dalam 14 hari ke depan.
                        Silakan ajukan waktu custom ke guru di bawah, atau gunakan opsi "Pindah Hari" manual dari halaman detail kelas.
                    </div>
                @else
                    <p class="text-muted small mb-2">Pilih salah satu jadwal berikut — murid akan didaftarkan otomatis dan sistem mengirim konfirmasi via WA (balas YA/TIDAK).</p>
                    <div class="list-group">
                        @foreach ($slots as $slot)
                            <form action="{{ route('jadwal.jadwal-kelas.sesi-murid.pindah', $sesiMurid->id) }}" method="POST" class="list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2">
                                @csrf
                                <input type="hidden" name="jadwal_kelas_id" value="{{ $slot['jadwal_kelas']->id }}">
                                <input type="hidden" name="tanggal" value="{{ $slot['tanggal']->toDateString() }}">
                                <div>
                                    <div class="fw-semibold">{{ $slot['jadwal_kelas']->name ?: $slot['jadwal_kelas']->mataPelajaran->name }}</div>
                                    <div class="text-muted small">
                                        {{ $slot['tanggal']->translatedFormat('l, d M Y') }}, jam {{ substr($slot['jam_mulai'], 0, 5) }}-{{ substr($slot['jam_selesai'], 0, 5) }} &middot;
                                        {{ $slot['jadwal_kelas']->branchOffice->name ?? '-' }} &middot;
                                        Guru: {{ $slot['jadwal_kelas']->guru->name ?? 'Belum ditentukan' }}
                                        @if ($slot['sisa_kapasitas'] !== null)
                                            &middot; Sisa kursi: {{ $slot['sisa_kapasitas'] }}
                                        @endif
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Pindahkan {{ $muridName }} ke jadwal ini dan kirim konfirmasi WA?');">
                                    Pilih & Kirim Konfirmasi
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h5 class="mb-1">Atau Ajukan Waktu Custom ke Guru</h5>
                <p class="text-muted small mb-3">
                    Untuk jadwal makeup di luar kelas yang sudah ada — sistem cek dulu apakah guru
                    <strong>{{ $originalKelas->guru->name ?? 'kelas ini' }}</strong> bentrok jadwal lain di waktu yang diajukan.
                    Kalau kosong, guru akan ditanya via WA (balas IYA/TIDAK) sebelum benar-benar diterapkan.
                </p>
                @if (! $originalKelas->guru)
                    <div class="alert alert-secondary mb-0">Kelas ini belum punya guru, jadi tidak ada yang bisa diajukan konfirmasinya.</div>
                @else
                    <form action="{{ route('jadwal.jadwal-kelas.sesi-murid.usulan', $sesiMurid->id) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal_usulan" class="form-control" min="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jam Mulai</label>
                            <input type="time" name="jam_mulai_usulan" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" name="jam_selesai_usulan" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Ajukan</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan (opsional)</label>
                            <input type="text" name="catatan" class="form-control" placeholder="Misal: makeup karena sakit minggu lalu">
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <a href="{{ route('jadwal.jadwal-kelas.show', $originalKelas->id) }}" class="btn btn-light mt-3">
            <i class="ri-arrow-left-line"></i> Kembali ke Detail Kelas
        </a>

    </div>
</div>
@endsection
