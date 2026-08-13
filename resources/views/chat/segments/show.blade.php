@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $segment->name }}</h4>
            <p class="text-muted mb-0">
                {{ $segment->description ?: 'Anggota segmen ini dihitung otomatis dari data terkini — daftar di bawah selalu mengikuti kondisi filter, bukan daftar tetap.' }}
            </p>
        </div>
        <a href="{{ route('chat.segments.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Nomor</th>
                            <th>Cabang</th>
                            <th>Terakhir Dihubungi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="fw-semibold">{{ $customer->name ?: '-' }}</td>
                                <td>+{{ $customer->phone }}</td>
                                <td>{{ $customer->branchOffice->name ?? '-' }}</td>
                                <td class="text-muted small">{{ $customer->last_contacted_at?->translatedFormat('d M Y, H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('chat.contacts.show', ['customer' => $customer->id]) }}" class="btn btn-sm btn-light" title="Lihat Customer 360">
                                        <i class="ri-user-3-line"></i> 360
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada pelanggan yang cocok dengan segmen ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $customers->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
