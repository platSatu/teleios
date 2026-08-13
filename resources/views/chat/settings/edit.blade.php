@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12 col-xl-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('chat.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Outer wrapper card: holds every settings section plus the
                 submit button, so the button visually belongs to this page
                 instead of floating loose below the last section. The
                 button lives in this card's own .card-footer (not the page
                 footer), and mb-4/mb-5 below keeps the whole card from
                 touching the site footer underneath it. --}}
            <div class="card mb-4 mb-md-5">
                <div class="card-body">

                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="mb-1">SLA Percakapan</h4>
                            <p class="text-muted mb-3">Batas waktu respon &amp; penyelesaian yang dipakai untuk menandai percakapan "terlambat" di halaman Percakapan &amp; Laporan.</p>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Batas Respon Pertama (menit)</label>
                                    <input type="number" min="1" name="chat_sla_first_response_minutes" class="form-control @error('chat_sla_first_response_minutes') is-invalid @enderror"
                                        value="{{ old('chat_sla_first_response_minutes', $company->chat_sla_first_response_minutes) }}" placeholder="Default: 15">
                                    @error('chat_sla_first_response_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Batas Penyelesaian (menit)</label>
                                    <input type="number" min="1" name="chat_sla_resolution_minutes" class="form-control @error('chat_sla_resolution_minutes') is-invalid @enderror"
                                        value="{{ old('chat_sla_resolution_minutes', $company->chat_sla_resolution_minutes) }}" placeholder="Default: 1440 (24 jam)">
                                    @error('chat_sla_resolution_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="mb-1">Broadcast Anti-Ban</h4>
                            <p class="text-muted mb-3">Batas maksimal pesan broadcast yang dikirim per menit dari satu nomor — melindungi nomor dari risiko diblokir WhatsApp.</p>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Maksimal Pesan / Menit / Device</label>
                                <input type="number" min="1" name="chat_broadcast_max_per_minute" class="form-control @error('chat_broadcast_max_per_minute') is-invalid @enderror"
                                    value="{{ old('chat_broadcast_max_per_minute', $company->chat_broadcast_max_per_minute) }}" placeholder="Default: 10">
                                @error('chat_broadcast_max_per_minute')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="card mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                <h4 class="mb-0">Survei Kepuasan Pelanggan (CSAT)</h4>
                                <div class="form-check form-switch mb-0">
                                    <input type="hidden" name="csat_enabled" value="0">
                                    <input type="checkbox" class="form-check-input" role="switch" id="wa-csat-enabled" name="csat_enabled" value="1"
                                        {{ old('csat_enabled', $company->csat_enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="wa-csat-enabled">Aktifkan</label>
                                </div>
                            </div>
                            <p class="text-muted mb-3">Saat aktif, sistem otomatis mengirim jajak pendapat (poll) 1-5 bintang ke pelanggan setiap kali percakapan ditandai selesai.</p>

                            <label class="form-label">Pertanyaan Survei</label>
                            <textarea name="csat_question" rows="2" class="form-control @error('csat_question') is-invalid @enderror"
                                placeholder="Seberapa puas Anda dengan layanan kami pada percakapan ini?">{{ old('csat_question', $company->csat_question) }}</textarea>
                            @error('csat_question')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="text-muted fs-12 mt-1 mb-0">Kosongkan untuk memakai pertanyaan default di atas.</p>
                        </div>
                    </div>

                </div>

                <div class="card-footer bg-transparent d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan Pengaturan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
