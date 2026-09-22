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
                <h4 class="mb-1">Laporan Tagihan</h4>
                <p class="text-muted mb-3">Histori seluruh invoice, lintas semua Tagihan.</p>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama pelanggan..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="belum_bayar" @selected(request('status') == 'belum_bayar')>Belum Bayar</option>
                        <option value="lunas" @selected(request('status') == 'lunas')>Lunas</option>
                        <option value="kadaluarsa" @selected(request('status') == 'kadaluarsa')>Kadaluarsa</option>
                        <option value="dibatalkan" @selected(request('status') == 'dibatalkan')>Dibatalkan</option>
                    </select>
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pelanggan</th>
                                <th>Tagihan</th>
                                <th>Kategori</th>
                                <th>Nominal</th>
                                <th>Denda</th>
                                <th>Status</th>
                                <th>Dibayar</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($penerimaList as $p)
                                <tr>
                                    <td class="fw-semibold">{{ $p->pelanggan->name ?? '-' }}</td>
                                    <td>{{ $p->tagihan->name ?? '-' }}</td>
                                    <td>{{ $p->tagihan->category->name ?? '-' }}</td>
                                    <td>Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($p->denda_amount, 0, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $statusBadge = [
                                                'belum_bayar' => 'bg-warning-subtle text-warning',
                                                'lunas' => 'bg-success-subtle text-success',
                                                'kadaluarsa' => 'bg-secondary-subtle text-secondary',
                                                'dibatalkan' => 'bg-danger-subtle text-danger',
                                            ][$p->status] ?? 'bg-secondary-subtle text-secondary';
                                        @endphp
                                        <span class="badge {{ $statusBadge }}">{{ str_replace('_', ' ', $p->status) }}</span>
                                    </td>
                                    <td>{{ $p->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('tagihan.laporan.show', $p->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $penerimaList->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
