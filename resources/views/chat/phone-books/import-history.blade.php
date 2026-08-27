@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Riwayat Import Buku Telepon</h4>
                        <p class="text-muted mb-0">
                            Setiap file yang diupload lewat tombol Import diproses di background
                            (App\Jobs\ProcessPhoneBookImport) — refresh halaman ini untuk melihat hasil
                            terbaru kalau statusnya masih "Diproses".
                        </p>
                    </div>
                    <a href="{{ route('chat.phone-books.index') }}" class="btn btn-light">
                        <i class="ri-arrow-left-line"></i> Kembali ke Buku Telepon
                    </a>
                </div>

                @if ($imports->isEmpty())
                    <div class="alert alert-secondary mb-0">Belum ada riwayat import.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Waktu Upload</th>
                                    <th>File</th>
                                    <th>Diupload oleh</th>
                                    <th>Status</th>
                                    <th>Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($imports as $index => $import)
                                    <tr>
                                        <td class="text-nowrap">{{ $import->created_at?->format('d M Y H:i') }}</td>
                                        <td>{{ $import->original_filename }}</td>
                                        <td>{{ $import->user?->name ?? '—' }}</td>
                                        <td>
                                            @switch($import->status)
                                                @case(\App\Models\WaPhoneBookImport::STATUS_DONE)
                                                    <span class="badge bg-success-subtle text-success">Selesai</span>
                                                    @break
                                                @case(\App\Models\WaPhoneBookImport::STATUS_FAILED)
                                                    <span class="badge bg-danger-subtle text-danger">Gagal</span>
                                                    @break
                                                @case(\App\Models\WaPhoneBookImport::STATUS_PROCESSING)
                                                    <span class="badge bg-warning-subtle text-warning">Diproses</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary-subtle text-secondary">Menunggu</span>
                                            @endswitch
                                        </td>
                                        <td style="min-width: 320px;">
                                            @if ($import->status === \App\Models\WaPhoneBookImport::STATUS_DONE)
                                                @include('chat.phone-books._import-result', [
                                                    'createdCount' => $import->total_created,
                                                    'errors' => $import->errors_detail ?? [],
                                                    'skippedSheets' => $import->skipped_sheets_detail ?? [],
                                                    'autoOpenErrors' => $index === 0,
                                                ])
                                            @elseif ($import->status === \App\Models\WaPhoneBookImport::STATUS_FAILED)
                                                <span class="text-danger small">{{ $import->failure_message ?? 'Import gagal diproses karena kesalahan tak terduga.' }}</span>
                                            @else
                                                <span class="text-muted small">Belum selesai diproses.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $imports->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
