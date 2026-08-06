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
                        <h4 class="mb-1">Balasan Cepat</h4>
                        <p class="text-muted mb-0">Template pesan siap-pakai untuk disisipkan ke kotak chat. Ketik "/" lalu shortcut di kotak pesan inbox untuk memakainya.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuickReplyModal">
                        <i class="ri-add-line"></i> Tambah Balasan Cepat
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Judul</th>
                                <th>Shortcut</th>
                                <th>Kategori</th>
                                <th>Isi</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quickReplies as $quickReply)
                                <tr>
                                    <td>{{ $quickReply->title }}</td>
                                    <td>{{ $quickReply->shortcut ? '/'.$quickReply->shortcut : '-' }}</td>
                                    <td><span class="badge bg-info-subtle text-info text-capitalize">{{ $quickReply->category }}</span></td>
                                    <td class="text-truncate" style="max-width: 240px;">{{ $quickReply->message_content }}</td>
                                    <td>
                                        <span class="badge {{ $quickReply->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $quickReply->status }}</span>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex flex-nowrap align-items-center justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editQuickReplyModal{{ $quickReply->id }}" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <form action="{{ route('chat.message-quick-replies.destroy', $quickReply->id) }}" method="POST" onsubmit="return confirm('Hapus balasan cepat ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editQuickReplyModal{{ $quickReply->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('chat.message-quick-replies.update', $quickReply->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Balasan Cepat</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('chat.message-quick-replies._form', ['quickReply' => $quickReply, 'errorBag' => 'editQuickReply'.$quickReply->id])
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
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada balasan cepat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $quickReplies->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addQuickReplyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('chat.message-quick-replies.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Balasan Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('chat.message-quick-replies._form', ['quickReply' => null, 'errorBag' => 'newQuickReply'])
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
