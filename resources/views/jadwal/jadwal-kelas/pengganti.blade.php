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

        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">Cari Guru Pengganti</h4>
                <p class="text-muted mb-3">
                    {{ $sesi->jadwalKelas->name ?: $sesi->jadwalKelas->mataPelajaran->name }} &middot;
                    {{ \Illuminate\Support\Carbon::parse($sesi->tanggal)->translatedFormat('l, d M Y') }} &middot;
                    {{ substr($sesi->jam_mulai_override ?: $sesi->jadwalKelas->jam_mulai, 0, 5) }}-{{ substr($sesi->jam_selesai_override ?: $sesi->jadwalKelas->jam_selesai, 0, 5) }} &middot;
                    Guru biasa: {{ $sesi->jadwalKelas->guru->name ?? 'Belum ditentukan' }}
                </p>

                @if ($sesi->guru_status === 'diganti')
                    <div class="alert alert-warning">
                        Sesi ini sudah digantikan oleh <strong>{{ $sesi->guruPengganti->name ?? '-' }}</strong>. Pilih guru lain di bawah untuk mengganti pilihan.
                    </div>
                @endif

                <form action="{{ route('jadwal.jadwal-kelas.sesi.assign-pengganti', $sesi->id) }}" method="POST">
                    @csrf

                    @if ($suggestions->isNotEmpty())
                        <label class="form-label fw-semibold">Direkomendasikan sistem (tersedia di jam ini, sudah pernah mengajar mata pelajaran ini)</label>
                        <div class="list-group mb-3">
                            @foreach ($suggestions as $candidate)
                                <label class="list-group-item d-flex align-items-center gap-2">
                                    <input type="radio" name="guru_pengganti_user_id" value="{{ $candidate->id }}" class="form-check-input mt-0" required>
                                    <span>
                                        {{ $candidate->name }}
                                        <span class="text-muted small">{{ $candidate->handphone ?? '-' }}</span>
                                        @if ($candidate->mengajar_mata_pelajaran_sama)
                                            <span class="badge bg-success-subtle text-success ms-1">Sudah mengajar mapel ini</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-secondary">
                            Sistem tidak menemukan guru yang otomatis tersedia & sesuai di jam ini. Silakan pilih manual dari daftar tim di bawah.
                        </div>
                    @endif

                    @if ($allTeamMembers->isNotEmpty())
                        <label class="form-label fw-semibold">Atau pilih manual dari tim</label>
                        <select name="guru_pengganti_user_id_manual" class="form-select mb-1" onchange="document.getElementById('manualRadio').checked = true; document.getElementById('manualRadio').value = this.value;">
                            <option value="">-- Pilih Anggota Tim --</option>
                            @foreach ($allTeamMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}{{ $member->handphone ? ' — ' . $member->handphone : '' }}</option>
                            @endforeach
                        </select>
                        <input type="radio" id="manualRadio" name="guru_pengganti_user_id" value="" class="d-none">
                        <div class="form-text mb-3">Memilih dari daftar ini akan menimpa pilihan rekomendasi di atas.</div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Tetapkan Guru Pengganti</button>
                        <a href="{{ route('jadwal.jadwal-kelas.guru.show', $sesi->jadwalKelas->guru_user_id) }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    // Manual dropdown wins if the user picks something from it (last
    // interacted control), otherwise whichever recommendation radio the
    // user clicked stands — plain radios can't express "these two
    // groups are mutually exclusive" on their own since they already
    // share the `name` attribute, so this just keeps the manual
    // <select>'s implicit hidden radio in sync when it's touched.
    document.querySelectorAll('input[name="guru_pengganti_user_id"]').forEach(function (radio) {
        if (radio.id !== 'manualRadio') {
            radio.addEventListener('change', function () {
                var manualSelect = document.querySelector('select[name="guru_pengganti_user_id_manual"]');
                if (manualSelect) manualSelect.value = '';
            });
        }
    });
</script>
@endsection
