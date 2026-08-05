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
                        <h4 class="mb-1">Label</h4>
                        <p class="text-muted mb-0">Kelola label yang bisa ditempelkan ke chat di Inbox (mis. "Prospek", "VIP", "Sudah Bayar").</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLabelModal">
                        <i class="ri-add-line"></i> Tambah Label
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Warna</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($labels as $label)
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: {{ $label->color }}; color: #fff;">{{ $label->name }}</span>
                                    </td>
                                    <td>
                                        <span class="d-inline-block rounded-circle me-2" style="width: 16px; height: 16px; background-color: {{ $label->color }}; vertical-align: middle;"></span>
                                        <span class="text-muted fs-13">{{ strtoupper($label->color) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editLabelModal{{ $label->id }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <form action="{{ route('chat.labels.destroy', $label->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus label ini? Label akan lepas dari semua chat yang sudah ditandai.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editLabelModal{{ $label->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('chat.labels.update', $label->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Label</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('chat.labels._form', ['label' => $label, 'errorBag' => 'editLabel'.$label->id])
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
                                    <td colspan="3" class="text-center text-muted py-4">Belum ada label. Klik "Tambah Label" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addLabelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('chat.labels.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Label</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('chat.labels._form', ['label' => null, 'errorBag' => 'newLabel'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
