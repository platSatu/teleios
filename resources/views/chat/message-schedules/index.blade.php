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

        {{-- Summary cards — fixed to the company's whole dataset (see
             MessageScheduleController::index()'s $stats), not affected
             by the search/status filter below, so they read as an
             at-a-glance dashboard rather than numbers that shift while
             someone's mid-search. --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-primary-subtle text-primary flex-shrink-0">
                            <i class="ri-calendar-schedule-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Jadwal</div>
                            <h4 class="mb-0">{{ $stats['total'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-warning-subtle text-warning flex-shrink-0">
                            <i class="ri-broadcast-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Jadwal Berjalan</div>
                            <h4 class="mb-0">{{ $stats['active'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-success-subtle text-success flex-shrink-0">
                            <i class="ri-check-double-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Terkirim</div>
                            <h4 class="mb-0">{{ $stats['delivered'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-danger-subtle text-danger flex-shrink-0">
                            <i class="ri-error-warning-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Gagal</div>
                            <h4 class="mb-0">{{ $stats['failed'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama jadwal..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status'))
                        <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                {{-- min-width per column (not just on the table) is what
                     actually keeps this readable: without it, a narrow
                     viewport doesn't scroll — it squeezes every <td>
                     instead, which is what was forcing badges/buttons to
                     wrap into a cramped stack. table-responsive still
                     scrolls horizontally past the sum of these widths on
                     small screens (intentional — the user explicitly
                     prefers a horizontal scrollbar over misaligned/
                     stacked columns), it just no longer also shrinks
                     individual columns below a usable width first. --}}
                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 1450px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:1%; white-space: nowrap;">No</th>
                                <th style="min-width: 160px;">Name</th>
                                <th style="min-width: 150px;">Kategori</th>
                                <th style="min-width: 160px;">Tujuan</th>
                                <th style="min-width: 90px;">Audiens</th>
                                <th style="min-width: 170px;">Tanggal Mulai - Berakhir</th>
                                <th style="min-width: 130px;">Status Kirim</th>
                                <th style="min-width: 100px;">Delivered</th>
                                <th style="min-width: 100px;">Read</th>
                                <th style="min-width: 100px;">Failed</th>
                                <th class="text-end" style="min-width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                                @php
                                    $recipients = collect($schedule->recipients ?? []);
                                    $phoneCount = $recipients->where('type', 'phone')->count();
                                    $groupCount = $recipients->where('type', 'group')->count();
                                    $userCount = $recipients->where('type', 'user')->count();
                                    $isRunning = $schedule->status === 'active'
                                        && $schedule->isActiveOn(now()->toDateString());
                                    $isDone = $schedule->status === 'active'
                                        && now()->toDateString() > $schedule->date_end->toDateString()
                                        && $schedule->pending_count == 0;
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
                                        <span class="badge bg-primary-subtle text-primary">{{ $schedule->audience_count }}</span>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        {{ $schedule->date_start->translatedFormat('d M Y') }}
                                        @if(!$schedule->date_start->equalTo($schedule->date_end))
                                            &ndash; {{ $schedule->date_end->translatedFormat('d M Y') }}
                                        @endif
                                        <div class="text-muted small">Jam {{ \Illuminate\Support\Carbon::parse($schedule->schedule_time)->format('H:i') }}</div>
                                        @if(!$schedule->date_start->equalTo($schedule->date_end))
                                            <span class="badge bg-warning-subtle text-warning mt-1">Berulang</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            @if($schedule->status !== 'active')
                                                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                            @elseif($isDone)
                                                <span class="badge bg-success-subtle text-success">Selesai</span>
                                            @elseif($isRunning)
                                                <span class="badge bg-warning-subtle text-warning">Berjalan</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info">Terjadwal</span>
                                            @endif
                                        </div>
                                        @if($schedule->pending_count)
                                            <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line"></i> {{ $schedule->pending_count }} Pending</span>
                                        @elseif(!$schedule->sent_count && !$schedule->failed_count)
                                            <span class="badge bg-secondary-subtle text-secondary"><i class="ri-hourglass-line"></i> Belum diproses</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($schedule->delivered_count)
                                            <span class="badge bg-success-subtle text-success"><i class="ri-check-double-line"></i> {{ $schedule->delivered_count }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($schedule->read_count)
                                            <span class="badge bg-info-subtle text-info"><i class="ri-eye-line"></i> {{ $schedule->read_count }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($schedule->failed_count)
                                            <span class="badge bg-danger-subtle text-danger"><i class="ri-error-warning-line"></i> {{ $schedule->failed_count }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        {{-- flex + nowrap so the 3 buttons stay in one row instead
                                             of wrapping into a cramped 2x2-looking cluster once the
                                             column's min-width above gives them room to sit
                                             side-by-side. --}}
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            <a href="{{ route('chat.message-schedules.history', $schedule->id) }}" class="btn btn-sm btn-light" title="History">
                                                <i class="ri-history-line"></i>
                                            </a>
                                            <a href="{{ route('chat.message-schedules.edit', $schedule->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('chat.message-schedules.destroy', $schedule->id) }}" method="POST" onsubmit="return confirm('Hapus jadwal ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">Belum ada pesan terjadwal. Klik "Tambah Jadwal" untuk membuat yang pertama.</td>
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
