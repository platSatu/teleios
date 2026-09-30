@extends('layouts.dashboard')

@section('content')
    @php
        $isFirst = $verifiedName === null;
        $holdHours = $settings['bank_account_hold_hours'];
        $changeDays = $settings['bank_account_change_days'];
    @endphp

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h4 class="mb-1">Rekening Pencairan</h4>
                    <p class="text-muted mb-0">Saldo Anda akan dikirim ke rekening ini setiap kali Anda tarik saldo.</p>
                </div>
                <a href="{{ route('wallet.withdrawal.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-bank-line"></i> Tarik Saldo</a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            {{-- Rekening aktif --}}
            @if ($current)
                <div class="card mb-3">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="fs-2 text-primary"><i class="ri-bank-line"></i></span>
                        <div>
                            <div class="fw-semibold fs-16">{{ $current->masked() }}</div>
                            <div>a.n. {{ $current->account_name }}</div>
                            <div class="text-success small">✅ Aktif · sejak {{ $current->active_at->translatedFormat('d M Y') }}</div>
                        </div>
                    </div>
                </div>
            @elseif (! $submission && ! $check)
                <div class="alert alert-light border mb-3">Anda belum punya rekening pencairan. Tambahkan dulu supaya bisa tarik saldo.</div>
            @endif

            {{-- Pengajuan terakhir yang belum/tidak aktif --}}
            @if ($submission)
                @php
                    [$badge, $label, $note] = match ($submission->state()) {
                        'scheduled' => ['bg-info-subtle text-info', '⏳ Segera aktif', 'Demi keamanan, rekening baru bisa dipakai mulai '.$submission->active_at->translatedFormat('d M Y, H:i').'.'],
                        'review' => ['bg-warning-subtle text-warning', '🔍 Sedang dicek tim kami', 'Nama di rekening ini berbeda dengan rekening Anda sebelumnya. Tim kami akan memeriksanya maksimal 1×24 jam kerja.'],
                        default => ['bg-danger-subtle text-danger', '❌ Tidak disetujui', 'Rekening ini tidak bisa dipakai. Alasan: '.$submission->review_note],
                    };
                @endphp
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold">{{ $submission->masked() }}</div>
                                <div class="small">a.n. {{ $submission->account_name }}</div>
                            </div>
                            <span class="badge {{ $badge }}">{{ $label }}</span>
                        </div>
                        <p class="text-muted small mt-2 mb-0">{{ $note }}</p>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    @if ($check)
                        {{-- Langkah 2: konfirmasi hasil cek bank --}}
                        <h6 class="mb-3">Rekening ditemukan</h6>
                        <div class="border rounded-3 p-3 mb-3">
                            <div>🏦 {{ $check['bank_name'] }} · {{ $check['account_number'] }}</div>
                            <div>Atas nama: <strong>{{ $check['account_name'] }}</strong></div>
                        </div>

                        @unless ($check['matched'])
                            <div class="alert alert-warning">
                                ⚠️ Nama rekening ini (<strong>{{ $check['account_name'] }}</strong>) berbeda dengan nama terverifikasi Anda (<strong>{{ $verifiedName }}</strong>). Rekening akan diperiksa tim kami terlebih dahulu sebelum bisa dipakai.
                            </div>
                        @endunless

                        <form action="{{ route('wallet.bank-account.store') }}" method="POST">
                            @csrf
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="confirm" value="1" id="bank-confirm" required>
                                <label class="form-check-label" for="bank-confirm">
                                    @if ($isFirst)
                                        Saya pastikan ini adalah <strong>rekening milik saya sendiri</strong>, dan saya mengerti nama <strong>{{ $check['account_name'] }}</strong> akan menjadi nama terverifikasi akun saya.
                                    @elseif ($check['matched'])
                                        Saya pastikan ini rekening milik saya sendiri.
                                    @else
                                        Saya mengerti dan tetap ingin mengajukan rekening ini.
                                    @endif
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" form="bank-cancel" class="btn btn-light">Kembali</button>
                                <button type="submit" class="btn btn-primary" id="bank-save" disabled>Simpan Rekening</button>
                            </div>
                        </form>
                        <form action="{{ route('wallet.bank-account.cancel') }}" method="POST" id="bank-cancel">
                            @csrf
                            @method('DELETE')
                        </form>
                    @elseif ($blockedReason)
                        <p class="text-muted mb-0">{{ $blockedReason }}</p>
                    @else
                        {{-- Langkah 1: bank + nomor + PIN --}}
                        <h6 class="mb-3">{{ $current ? 'Ganti Rekening' : 'Tambah Rekening' }}</h6>

                        @if ($isFirst)
                            <div class="alert alert-info small">
                                💡 <strong>Gunakan rekening atas nama Anda sendiri.</strong>
                                Nama pemilik rekening pertama akan menjadi <strong>nama terverifikasi</strong> akun Anda. Untuk ke depannya, Anda hanya bisa ganti ke rekening dengan nama yang sama.
                            </div>
                        @endif

                        <form action="{{ route('wallet.bank-account.check') }}" method="POST" class="row g-3" id="bank-check-form">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label" for="bank_code">Bank</label>
                                <select name="bank_code" id="bank_code" class="form-select" required>
                                    <option value="">Pilih bank tujuan</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank['bankCode'] }}" @selected(old('bank_code') === $bank['bankCode'])>{{ $bank['bankName'] }}</option>
                                    @endforeach
                                </select>
                                @if (! count($banks))
                                    <div class="form-text text-danger">Daftar bank belum bisa dimuat. Silakan coba beberapa menit lagi.</div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="account_number">Nomor Rekening</label>
                                <input type="text" name="account_number" id="account_number" class="form-control" inputmode="numeric" maxlength="30" value="{{ old('account_number') }}" required>
                                <div class="form-text">Tulis angka saja, tanpa spasi atau titik.</div>
                            </div>
                            <div class="col-md-6">
                                <x-transaction-pin-input />
                            </div>
                            <div class="col-12">
                                <p class="text-muted small">Nama pemilik rekening akan kami cek otomatis ke bank. Anda tidak perlu mengetiknya.</p>
                                <button type="submit" class="btn btn-primary">Cek Rekening</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('bank-confirm')?.addEventListener('change', function () {
            document.getElementById('bank-save').disabled = ! this.checked;
        });

        @if ($current && ! $check && ! $blockedReason)
            document.getElementById('bank-check-form')?.addEventListener('submit', function (event) {
                var message = 'Yakin ingin ganti rekening?\n\nSetelah diganti, rekening baru baru bisa dipakai {{ $holdHours }} jam lagi, dan Anda baru bisa ganti lagi setelah {{ $changeDays }} hari. Ini untuk melindungi saldo Anda kalau suatu saat akun Anda dipakai orang lain.';
                if (! confirm(message)) {
                    event.preventDefault();
                }
            });
        @endif
    </script>
@endsection
