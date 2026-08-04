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
                        <h4 class="mb-1">Pesan Terjadwal</h4>
                        <p class="text-muted mb-0">Sekali kirim, berulang setiap hari, atau bertahap per kontak (drip) — semua dari satu tempat.</p>
                    </div>
                    <a href="{{ route('chat.message-schedules.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Jadwal
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:1%">No</th>
                                <th>Name</th>
                                <th>Kategori</th>
                                <th>Tujuan</th>
                                <th>Tanggal Mulai - Berakhir</th>
                                <th>Status Kirim</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                                @php
                                    $recipients = collect($schedule->recipients ?? []);
                                    $phoneCount = $recipients->where('type', 'phone')->count();
                                    $groupCount = $recipients->where('type', 'group')->count();
                                    $userCount = $recipients->where('type', 'user')->count();
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration + ($schedules->currentPage() - 1) * $schedules->perPage() }}</td>
                                    <td>
                                        {{ $schedule->title }}
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <span class="badge {{ $schedule->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $schedule->status }}</span>
                                            <span class="badge bg-dark-subtle text-dark">
                                                @if($schedule->type === 'once') <i class="ri-send-plane-line"></i> Sekali Kirim
                                                @elseif($schedule->type === 'drip') <i class="ri-flow-chart"></i> Drip
                                                @else <i class="ri-repeat-line"></i> Berulang
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($schedule->type === 'drip')
                                            <span class="badge bg-info-subtle text-info"><i class="ri-flow-chart"></i> {{ $schedule->steps_count }} Langkah</span>
                                        @elseif($schedule->use_template)
                                            <span class="badge bg-info-subtle text-info"><i class="ri-file-list-3-line"></i> {{ $schedule->waMessageTemplate->name ?? 'Template dihapus' }}</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info text-capitalize">{{ $schedule->category_schedule }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($phoneCount) <span class="badge bg-primary-subtle text-primary"><i class="ri-smartphone-line"></i> {{ $phoneCount }} Nomor</span> @endif
                                            @if($groupCount) <span class="badge bg-primary-subtle text-primary"><i class="ri-group-line"></i> {{ $groupCount }} Grup</span> @endif
                                            @if($userCount) <span class="badge bg-primary-subtle text-primary"><i class="ri-team-line"></i> {{ $userCount }} User</span> @endif
                                            @if(!$phoneCount && !$groupCount && !$userCount) <span class="text-muted">-</span> @endif
                                        </div>
                                    </td>
                                    <td>
                                        {{ $schedule->date_start->translatedFormat('d M Y') }}
                                        @if(!$schedule->date_start->equalTo($schedule->date_end))
                                            &ndash; {{ $schedule->date_end->translatedFormat('d M Y') }}
                                            <span class="badge bg-warning-subtle text-warning">Berulang</span>
                                        @endif
                                        <div class="text-muted small">Jam {{ \Illuminate\Support\Carbon::parse($schedule->schedule_time)->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($schedule->sent_count) <span class="badge bg-success-subtle text-success"><i class="ri-check-double-line"></i> {{ $schedule->sent_count }}</span> @endif
                                            @if($schedule->failed_count) <span class="badge bg-danger-subtle text-danger"><i class="ri-error-warning-line"></i> {{ $schedule->failed_count }}</span> @endif
                                            @if($schedule->pending_count) <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line"></i> {{ $schedule->pending_count }}</span> @endif
                                            @if(!$schedule->sent_count && !$schedule->failed_count && !$schedule->pending_count)
                                                <span class="badge bg-secondary-subtle text-secondary"><i class="ri-hourglass-line"></i> Belum diproses</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('chat.message-schedules.history', $schedule->id) }}" class="btn btn-sm btn-light" title="History">
                                            <i class="ri-history-line"></i>
                                        </a>
                                        <a href="{{ route('chat.message-schedules.edit', $schedule->id) }}" class="btn btn-sm btn-light" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('chat.message-schedules.destroy', $schedule->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada pesan terjadwal. Klik "Tambah Jadwal" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $schedules->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
