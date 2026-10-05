@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Live Chat Widget</h4>
                        <p class="text-muted mb-0">Pasang chat di website Anda. AI menjawab otomatis, tim Anda bisa mengambil alih dari <a href="{{ route('chat.widget-inbox.index') }}">Live Chat Inbox</a>.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cwModalnew">
                        <i class="ri-add-line"></i> Buat Widget
                    </button>
                </div>

                @forelse ($widgets as $widget)
                    @php
                        $snippet = '<script src="'.asset('widget.js').'" data-key="'.$widget->public_key.'" data-color="'.$widget->setting('color').'" data-position="'.$widget->setting('position').'" async></script>';
                    @endphp
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="mb-1">
                                    {{ $widget->name }}
                                    <span class="badge {{ $widget->isActive() ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $widget->isActive() ? 'Aktif' : 'Nonaktif' }}</span>
                                </h6>
                                <div class="small text-muted">
                                    {{ implode(', ', $widget->allowed_domains ?? []) }} &middot; {{ $widget->branchOffice->name ?? 'Pusat' }} &middot; {{ $widget->conversations_count }} percakapan
                                </div>
                                <div class="small mt-1">
                                    @if ($widget->last_seen_at)
                                        <span class="text-success">&#10003; Terpasang</span> <span class="text-muted">(terakhir dibuka {{ $widget->last_seen_at->diffForHumans() }}{{ $widget->last_seen_domain ? ' di '.$widget->last_seen_domain : '' }})</span>
                                    @else
                                        <span class="text-warning">Belum terdeteksi di website</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-1 align-items-start">
                                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#cwModal{{ $widget->id }}"><i class="ri-edit-line"></i> Edit</button>
                                <form action="{{ route('chat.widgets.regenerate-key', $widget->id) }}" method="POST" onsubmit="return confirm('Ganti key? Kode lama di website langsung tidak berfungsi.');">
                                    @csrf
                                    <button class="btn btn-sm btn-light"><i class="ri-key-2-line"></i> Ganti key</button>
                                </form>
                                <form action="{{ route('chat.widgets.destroy', $widget->id) }}" method="POST" onsubmit="return confirm('Hapus widget ini beserta riwayat chatnya?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </div>
                        </div>

                        <label class="form-label small mt-3 mb-1">Kode pemasangan &mdash; tempel sebelum <code>&lt;/body&gt;</code> di website Anda</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" value="{{ $snippet }}" readonly id="cwSnippet{{ $widget->id }}">
                            <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cwSnippet{{ $widget->id }}').value); this.textContent='Tersalin';">Salin</button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="ri-chat-smile-2-line fs-1 text-primary"></i>
                        <h5 class="mt-2">Pasang chat di website Anda dalam 3 menit</h5>
                        <p class="text-muted">Buat widget, salin 1 baris kode, tempel di website. Selesai.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cwModalnew">Buat Widget</button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@foreach ($widgets->prepend(null) as $widget)
    <div class="modal fade" id="cwModal{{ $widget->id ?? 'new' }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ $widget ? route('chat.widgets.update', $widget->id) : route('chat.widgets.store') }}" method="POST">
                    @csrf
                    @if ($widget)
                        @method('PUT')
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $widget ? 'Edit Widget' : 'Buat Widget' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('chat.widgets._form', ['errorBag' => $widget ? 'editWidget'.$widget->id : 'newWidget'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Buka lagi modal yang gagal validasi. --}}
@php($failedBag = collect($errors->getBags())->keys()->first(fn ($bag) => str_starts_with($bag, 'newWidget') || str_starts_with($bag, 'editWidget')))
@if ($failedBag)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var id = @json($failedBag === 'newWidget' ? 'new' : substr($failedBag, strlen('editWidget')));
            var modal = document.getElementById('cwModal' + id);
            if (modal && window.bootstrap) new bootstrap.Modal(modal).show();
        });
    </script>
@endif
@endsection
