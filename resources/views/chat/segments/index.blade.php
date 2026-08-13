@extends('layouts.dashboard')

@php
    $tagColors = ['secondary', 'primary', 'success', 'danger', 'warning', 'info', 'dark'];
@endphp

@section('content')
<div class="row g-3">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Tag catalog --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Kelola Tag</h5>
                <p class="text-muted small mb-3">Tag dipasang ke pelanggan dari halaman Customer 360, dan dipakai sebagai filter segmen di sini.</p>

                <form action="{{ route('chat.customer-tags.store') }}" method="POST" class="d-flex gap-1 mb-3">
                    @csrf
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama tag, mis. VIP" required maxlength="50">
                    <select name="color" class="form-select form-select-sm" style="max-width: 120px;">
                        @foreach ($tagColors as $color)
                            <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ri-add-line"></i></button>
                </form>

                <div class="d-flex flex-column gap-2">
                    @forelse ($tags as $tag)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2">
                            <span class="badge bg-{{ $tag->color }}-subtle text-{{ $tag->color }}">{{ $tag->name }}</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">{{ $tag->customers_count }} pelanggan</span>
                                <form action="{{ route('chat.customer-tags.destroy', $tag->id) }}" method="POST" onsubmit="return confirm('Hapus tag ini? Tag akan lepas dari semua pelanggan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger p-1" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada tag.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Segments --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="mb-1">Buat Segmen Baru</h5>
                <p class="text-muted small mb-3">Segmen adalah filter tersimpan — anggotanya selalu dihitung ulang otomatis, bukan daftar tetap. Isi kombinasi kondisi yang diinginkan (boleh lebih dari satu, semua kondisi harus terpenuhi).</p>

                <form action="{{ route('chat.segments.store') }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small">Nama Segmen</label>
                        <input type="text" name="name" class="form-control form-control-sm" required maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Deskripsi (opsional)</label>
                        <input type="text" name="description" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Punya Tag</label>
                        <select name="tag_id" class="form-select form-select-sm">
                            <option value="">- Tidak difilter -</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tahap Deal</label>
                        <select name="deal_stage" class="form-select form-select-sm">
                            <option value="">- Tidak difilter -</option>
                            @foreach ($dealStages as $stageValue => $stageLabel)
                                <option value="{{ $stageValue }}">{{ $stageLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cabang</label>
                        <select name="branch_office_id" class="form-select form-select-sm">
                            <option value="">- Tidak difilter -</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tidak Dihubungi (hari)</label>
                        <input type="number" name="no_contact_days" class="form-control form-control-sm" min="1" placeholder="mis. 30">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input type="checkbox" name="has_overdue_task" value="1" id="wa-segment-overdue" class="form-check-input">
                            <label for="wa-segment-overdue" class="form-check-label small">Hanya yang punya tugas follow-up terlambat</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ri-add-line"></i> Buat Segmen</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Daftar Segmen</h5>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Deskripsi</th>
                                <th>Anggota</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($segments as $segment)
                                <tr>
                                    <td class="fw-semibold">{{ $segment->name }}</td>
                                    <td class="text-muted small">{{ $segment->description ?: '-' }}</td>
                                    <td>
                                        <a href="{{ route('chat.segments.show', $segment->id) }}" class="badge bg-primary-subtle text-primary text-decoration-none">
                                            {{ $segment->member_count }} pelanggan
                                        </a>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            <a href="{{ route('chat.segments.edit', $segment->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('chat.segments.destroy', $segment->id) }}" method="POST" onsubmit="return confirm('Hapus segmen ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada segmen. Buat dari form di atas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
