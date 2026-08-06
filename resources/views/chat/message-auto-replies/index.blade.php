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
                        <h4 class="mb-1">Auto Reply</h4>
                        <p class="text-muted mb-0">Balas otomatis saat pesan masuk mengandung (atau sama persis dengan) kata kunci tertentu.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAutoReplyModal">
                        <i class="ri-add-line"></i> Tambah Auto Reply
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kata Kunci</th>
                                <th>Cara Cocok</th>
                                <th>Balasan</th>
                                <th>Status</th>
                                <th>Aktivitas</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($autoReplies as $autoReply)
                                <tr>
                                    <td>
                                        @if($autoReply->is_default)
                                            <span class="badge bg-info-subtle text-info"><i class="ri-star-line"></i> Default</span>
                                        @else
                                            <code>{{ $autoReply->keyword }}</code>
                                        @endif
                                    </td>
                                    <td class="text-capitalize">{{ $autoReply->is_default ? '—' : ($autoReply->match_type === 'exact' ? 'Sama Persis' : 'Mengandung') }}</td>
                                    <td class="text-truncate" style="max-width: 280px;">{{ $autoReply->reply_message }}</td>
                                    <td>
                                        <span class="badge {{ $autoReply->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $autoReply->status }}</span>
                                    </td>
                                    <td>
                                        @if($autoReply->last_triggered_at)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="ri-check-double-line"></i> {{ $autoReply->trigger_count }}x terpicu
                                            </span>
                                            <div class="fs-11 text-muted mt-1">Terakhir {{ $autoReply->last_triggered_at->diffForHumans() }}</div>
                                        @elseif($autoReply->last_error)
                                            <span class="badge bg-danger-subtle text-danger" title="{{ $autoReply->last_error }}">
                                                <i class="ri-error-warning-line"></i> Gagal kirim
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                <i class="ri-hourglass-line"></i> Belum pernah
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        {{-- Aktivitas above is often 2 lines (badge + "terakhir ...
                                             yang lalu"), which makes this row taller than the rest —
                                             without this flex wrapper the two action buttons drift to
                                             the top of the cell instead of staying centered. --}}
                                        <div class="d-flex flex-nowrap align-items-center justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editAutoReplyModal{{ $autoReply->id }}" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <form action="{{ route('chat.message-auto-replies.destroy', $autoReply->id) }}" method="POST" onsubmit="return confirm('Hapus auto reply ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editAutoReplyModal{{ $autoReply->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form action="{{ route('chat.message-auto-replies.update', $autoReply->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Auto Reply</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('chat.message-auto-replies._form', ['autoReply' => $autoReply, 'errorBag' => 'editAutoReply'.$autoReply->id])
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
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada auto reply.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $autoReplies->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAutoReplyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('chat.message-auto-replies.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Auto Reply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('chat.message-auto-replies._form', ['autoReply' => null, 'errorBag' => 'newAutoReply'])
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
