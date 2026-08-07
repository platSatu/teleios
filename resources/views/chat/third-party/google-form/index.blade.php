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
                        <h4 class="mb-1">Third Party &mdash; Google Form</h4>
                        <p class="text-muted mb-0">
                            Terima jawaban Google Form sebagai webhook JSON, lalu balas otomatis lewat WhatsApp
                            memakai WA Template pilihanmu.
                        </p>
                    </div>
                    <a href="{{ route('chat.third-party.google-form.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Integrasi
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 950px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 200px;">Nama Integrasi</th>
                                <th style="min-width: 140px;">Device Pengirim</th>
                                <th style="min-width: 180px;">WA Template</th>
                                <th style="min-width: 160px;">Field Nomor WA</th>
                                <th style="min-width: 110px;">Submission</th>
                                <th style="min-width: 100px;">Status</th>
                                <th class="text-end" style="min-width: 180px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($integrations as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->name }}</td>
                                    <td><span class="text-muted small">{{ $item->device_id }}</span></td>
                                    <td>
                                        @if ($item->waMessageTemplate)
                                            {{ $item->waMessageTemplate->name }}
                                        @else
                                            <span class="text-danger small">Belum dipilih</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $item->target_number_field }}</code></td>
                                    <td>{{ $item->submissions_count }}</td>
                                    <td>
                                        <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">
                                            {{ $item->status }}
                                        </span>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        {{-- Icon-only btn-light + title tooltip, same pattern as
                                             Pesan Terjadwal (message-schedules/index.blade.php) and
                                             Kontak (phone-books/index.blade.php) — was a labeled
                                             btn-group/btn-outline-* here, the only page in Chat still
                                             styled that way. --}}
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            <a href="{{ route('chat.third-party.google-form.show', $item->id) }}" class="btn btn-sm btn-light" title="Detail">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="{{ route('chat.third-party.google-form.edit', $item->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <button type="submit" form="delete-gform-{{ $item->id }}" class="btn btn-sm btn-light text-danger" title="Hapus"
                                                onclick="return confirm('Hapus integrasi ini? Log submission-nya ikut terhapus.');">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                        <form id="delete-gform-{{ $item->id }}" action="{{ route('chat.third-party.google-form.destroy', $item->id) }}" method="POST" class="d-none">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Belum ada integrasi Google Form. Klik "Tambah Integrasi" untuk membuat yang pertama.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $integrations->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
