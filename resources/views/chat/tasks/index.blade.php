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
                        <h4 class="mb-1">Tugas &amp; Follow-up</h4>
                        <p class="text-muted mb-0">
                            Semua tugas follow-up pelanggan, lintas nomor WA. Buat tugas baru dari halaman
                            Customer 360 tiap pelanggan (Kontak / Buku Telepon &rarr; tombol "360").
                        </p>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama/nomor pelanggan..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="pending" @selected($status === 'pending')>Belum Selesai</option>
                        <option value="done" @selected($status === 'done')>Selesai</option>
                        <option value="all" @selected($status === 'all')>Semua</option>
                    </select>
                    <select name="assigned_to" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Assignee</option>
                        @foreach ($teamMembers as $member)
                            <option value="{{ $member->id }}" @selected(request('assigned_to') == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                    @if (! $lockedBranchId)
                        <select name="branch_office_id" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                            <option value="">Semua Cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_office_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <div class="form-check align-self-center">
                        <input type="checkbox" name="overdue" value="1" id="wa-task-overdue" class="form-check-input" onchange="this.form.submit()" @checked(request('overdue') === '1')>
                        <label for="wa-task-overdue" class="form-check-label">Hanya yang terlambat</label>
                    </div>
                    @if(request('search') || request('assigned_to') || request('branch_office_id') || request('overdue'))
                        <a href="{{ route('chat.tasks.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 900px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 180px;">Pelanggan</th>
                                <th style="min-width: 220px;">Tugas</th>
                                <th style="min-width: 160px;">Jatuh Tempo</th>
                                <th style="min-width: 180px;">Ditugaskan ke</th>
                                <th style="min-width: 100px;">Status</th>
                                <th class="text-end" style="min-width: 160px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tasks as $task)
                                @php
                                    $isOverdue = $task->status === 'pending' && $task->due_at && $task->due_at->isPast();
                                @endphp
                                <tr>
                                    <td>
                                        @if ($task->customer)
                                            <a href="{{ route('chat.contacts.show', ['customer' => $task->customer->id]) }}" class="fw-semibold">
                                                {{ $task->customer->name ?: ('+'.$task->customer->phone) }}
                                            </a>
                                        @else
                                            <span class="text-muted">Pelanggan dihapus</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $task->title }}</div>
                                        @if ($task->description)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($task->description, 80) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($task->due_at)
                                            <span class="{{ $isOverdue ? 'text-danger fw-semibold' : 'text-muted small' }}">
                                                @if ($isOverdue)
                                                    <i class="ri-alarm-warning-line"></i>
                                                @endif
                                                {{ $task->due_at->translatedFormat('d M Y, H:i') }}
                                            </span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $task->assignee->name ?? '-' }}</td>
                                    <td>
                                        @if ($task->status === 'done')
                                            <span class="badge bg-success-subtle text-success"><i class="ri-check-double-line"></i> Selesai</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line"></i> Belum Selesai</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            @if ($task->status === 'done')
                                                <form action="{{ route('chat.tasks.reopen', $task->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light" title="Buka Lagi">
                                                        <i class="ri-arrow-go-back-line"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('chat.tasks.complete', $task->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light text-success" title="Tandai Selesai">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('chat.tasks.destroy', $task->id) }}" method="POST" onsubmit="return confirm('Hapus tugas ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada tugas. Buat tugas dari halaman Customer 360 tiap pelanggan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $tasks->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
