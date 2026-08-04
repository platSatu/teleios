@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">History Pengiriman</h4>
            <p class="text-muted mb-0">
                "{{ $schedule->title }}" &middot;
                {{ $schedule->date_start->translatedFormat('d M Y') }}
                @if(!$schedule->date_start->equalTo($schedule->date_end))
                    &ndash; {{ $schedule->date_end->translatedFormat('d M Y') }}
                @endif
                &middot; Jam {{ \Illuminate\Support\Carbon::parse($schedule->schedule_time)->format('H:i') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('chat.message-schedules.edit', $schedule->id) }}" class="btn btn-light">
                <i class="ri-edit-line"></i> Edit Jadwal
            </a>
            <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal Kirim</th>
                            @if($schedule->type === 'drip')
                                <th>Langkah</th>
                            @endif
                            <th>Tujuan</th>
                            <th>Status</th>
                            <th>Percobaan</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->send_date->translatedFormat('d M Y') }}</td>
                                @if($schedule->type === 'drip')
                                    <td class="small">{{ $stepLabels[$log->step_order] ?? ('Langkah '.$log->step_order) }}</td>
                                @endif
                                <td>{{ $recipientLabels[$log->recipient_key] ?? $log->recipient_key }}</td>
                                <td>
                                    @if($log->status === 'sent')
                                        <span class="badge bg-success-subtle text-success"><i class="ri-check-double-line"></i> Terkirim</span>
                                    @elseif($log->status === 'failed')
                                        <span class="badge bg-danger-subtle text-danger"><i class="ri-error-warning-line"></i> Gagal</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line"></i> Menunggu</span>
                                    @endif
                                </td>
                                <td>{{ $log->attempts }}</td>
                                <td>
                                    @if($log->status === 'sent')
                                        <span class="text-muted small">{{ $log->sent_at?->translatedFormat('d M Y H:i') }}</span>
                                    @elseif($log->error)
                                        <span class="text-danger small">{{ \Illuminate\Support\Str::limit($log->error, 100) }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $schedule->type === 'drip' ? 6 : 5 }}" class="text-center text-muted py-4">Belum ada history pengiriman untuk jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $logs->links() }}</div>
        </div>
    </div>
</div>
@endsection
