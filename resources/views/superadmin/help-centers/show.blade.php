@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $helpCenter->number_ticket }} — {{ $helpCenter->name }}</h4>
            <span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $helpCenter->status }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('help-center.edit', $helpCenter->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('help-center.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%">Kategori</td>
                            <td>{{ $helpCenter->category->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pelapor</td>
                            <td>{{ $helpCenter->user->name ?? '— Tidak terikat user —' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuka</td>
                            <td>{{ $helpCenter->open_date?->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ditutup</td>
                            <td>{{ $helpCenter->close_date?->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                        @if ($helpCenter->attachment)
                            <tr>
                                <td class="text-muted">Lampiran</td>
                                <td>
                                    <a href="{{ asset('storage/' . $helpCenter->attachment) }}" target="_blank">
                                        <i class="ri-attachment-2"></i> Lihat file
                                    </a>
                                </td>
                            </tr>
                        @endif
                    </table>
                    <hr>
                    <p class="text-muted mb-0" style="white-space: pre-line;">{{ $helpCenter->description }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Percakapan</h5>

                    <div class="d-flex flex-column gap-3 mb-4" style="max-height: 420px; overflow-y: auto;">
                        @forelse ($helpCenter->answers as $answer)
                            @php $isAdmin = $answer->user_id !== $helpCenter->user_id; @endphp
                            <div class="d-flex {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }}">
                                <div class="p-2 px-3 rounded-3 {{ $isAdmin ? 'bg-primary-subtle' : 'bg-light' }}" style="max-width: 75%;">
                                    <div class="fs-12 fw-semibold {{ $isAdmin ? 'text-primary' : 'text-muted' }} mb-1">
                                        {{ $answer->user->name ?? '-' }} {{ $isAdmin ? '(Admin)' : '' }}
                                    </div>
                                    <div style="white-space: pre-line;">{{ $answer->answers }}</div>
                                    <div class="fs-11 text-muted mt-1">{{ $answer->date_answers?->format('d M Y H:i') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center py-4 mb-0">Belum ada balasan pada tiket ini.</p>
                        @endforelse
                    </div>

                    <form action="{{ route('help-center.reply', $helpCenter->id) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <textarea name="answers" class="form-control" rows="3" placeholder="Tulis balasan..." required>{{ old('answers') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-send-plane-2-line"></i> Kirim Balasan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
