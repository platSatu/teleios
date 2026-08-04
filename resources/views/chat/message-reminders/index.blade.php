@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Pengingat</h4>
                        <p class="text-muted mb-0">Kirim pesan pengingat otomatis pada waktu yang ditentukan.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReminderModal">
                        <i class="ri-add-line"></i> Tambah Pengingat
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Tujuan</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reminders as $reminder)
                                <tr>
                                    <td>{{ $reminder->title_reminder }}</td>
                                    <td>{{ $reminder->category_message_reminder }}</td>
                                    <td>
                                        @if($reminder->is_group)
                                            <span class="badge bg-secondary-subtle text-secondary">Grup: {{ $reminder->group_jid }}</span>
                                        @else
                                            {{ $reminder->phone_number ?: '-' }}
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($reminder->start_reminder)->translatedFormat('d M Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $reminder->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $reminder->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editReminderModal{{ $reminder->id }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <form action="{{ route('chat.message-reminders.destroy', $reminder->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pengingat ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editReminderModal{{ $reminder->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('chat.message-reminders.update', $reminder->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Pengingat</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('chat.message-reminders._form', ['reminder' => $reminder, 'errorBag' => 'editReminder'.$reminder->id, 'categories' => $categories])
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada pengingat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $reminders->links() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addReminderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('chat.message-reminders.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pengingat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('chat.message-reminders._form', ['reminder' => null, 'errorBag' => 'newReminder', 'categories' => $categories])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
