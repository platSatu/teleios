@extends('layouts.dashboard')

@section('content')
    @php
        $replySubject = rawurlencode('Re: '.$message->topic.' – Bizbos');
        $waText = rawurlencode('Halo '.$message->name.', terima kasih sudah menghubungi Bizbos terkait "'.$message->topic.'". ');
    @endphp
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('web.contact-messages.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali"><i class="ri-arrow-left-line"></i></a>
                <h4 class="mb-0">Pesan dari {{ $message->name }}</h4>
            </div>

            @include('components.notifikasi')

            <div class="card">
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold">{{ $message->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">No. HP</div>
                            <div class="fw-semibold">{{ $message->phone }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Topik</div>
                            <div class="fw-semibold">{{ $message->topic }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Dikirim</div>
                            <div class="fw-semibold">{{ $message->created_at->translatedFormat('d M Y H:i') }}</div>
                        </div>
                    </div>

                    <div class="text-muted small mb-1">Pesan</div>
                    <div class="border rounded p-3 bg-light mb-3" style="white-space: pre-line;">{{ $message->message }}</div>

                    <div class="text-muted small mb-4">
                        Email notifikasi: {{ $message->notified_at ? 'terkirim ke antrean '.$message->notified_at->translatedFormat('d M Y H:i') : 'tidak dikirim (email Pengaturan Web kosong)' }}
                        · IP {{ $message->ip_address ?? '-' }}
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a href="mailto:{{ $message->email }}?subject={{ $replySubject }}" class="btn btn-primary"><i class="ri-mail-send-line"></i> Balas via Email</a>
                        <a href="https://wa.me/{{ $message->whatsappNumber() }}?text={{ $waText }}" target="_blank" rel="noopener" class="btn btn-success"><i class="ri-whatsapp-line"></i> Balas via WhatsApp</a>

                        <form action="{{ route('web.contact-messages.status', $message->id) }}" method="POST" class="d-flex gap-2 ms-auto">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="form-select form-select-sm">
                                @foreach (\App\Models\WebContactMessage::STATUSES as $value => $label)
                                    <option value="{{ $value }}" @selected($message->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Ubah status</button>
                        </form>

                        <form action="{{ route('web.contact-messages.destroy', $message->id) }}" method="POST" onsubmit="return confirm('Hapus pesan ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
