@extends('layouts.dashboard')

@section('content')
    @php
        $badge = ['new' => 'bg-danger-subtle text-danger', 'replied' => 'bg-success-subtle text-success', 'archived' => 'bg-secondary-subtle text-secondary'];
    @endphp
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Pesan Masuk</h4>
                <p class="text-muted mb-0">Pesan dari form Kontak di website. Salinannya juga dikirim ke email di Pengaturan Web.</p>
            </div>

            @include('components.notifikasi')

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('web.contact-messages.index', array_filter(['search' => $search])) }}" class="btn btn-sm {{ $status ? 'btn-outline-secondary' : 'btn-secondary' }}">
                    Semua ({{ $counts->sum() }})
                </a>
                @foreach (\App\Models\WebContactMessage::STATUSES as $value => $label)
                    <a href="{{ route('web.contact-messages.index', array_filter(['status' => $value, 'search' => $search])) }}" class="btn btn-sm {{ $status === $value ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        {{ $label }} ({{ $counts->get($value, 0) }})
                    </a>
                @endforeach
                <form method="GET" class="ms-auto">
                    @if ($status)
                        <input type="hidden" name="status" value="{{ $status }}">
                    @endif
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, email, HP, isi..." value="{{ $search }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Pengirim</th>
                            <th>Topik</th>
                            <th>Pesan</th>
                            <th>Status</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $item)
                            <tr class="{{ $item->status === 'new' ? 'fw-semibold' : '' }}">
                                <td class="text-nowrap small">{{ $item->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div>{{ $item->name }}</div>
                                    <div class="text-muted small fw-normal">{{ $item->email }} · {{ $item->phone }}</div>
                                </td>
                                <td class="small">{{ $item->topic }}</td>
                                <td class="text-muted small fw-normal">{{ \Illuminate\Support\Str::limit($item->message, 80) }}</td>
                                <td><span class="badge {{ $badge[$item->status] ?? 'bg-light text-dark' }}">{{ $item->statusLabel() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('web.contact-messages.show', $item->id) }}" class="btn btn-sm btn-outline-primary"><i class="ri-eye-line"></i> Buka</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada pesan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($messages->hasPages())
                <div class="mt-3">{{ $messages->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
