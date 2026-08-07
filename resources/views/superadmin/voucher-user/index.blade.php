@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Voucher (Promo Code)</h4>
                    <p class="text-muted mb-0">Kode voucher yang bisa dipakai banyak user, dengan kuota &amp; masa berlaku.</p>
                </div>
                <a href="{{ route('voucher-user.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Voucher
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / kode voucher..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Kode</th>
                            <th>Diskon</th>
                            <th>Limit</th>
                            <th>Per User</th>
                            <th>Berlaku</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($voucherUsers as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td><code>{{ $item->kode_voucher }}</code></td>
                                <td>{{ rtrim(rtrim(number_format($item->percentase, 2, '.', ''), '0'), '.') }}%</td>
                                <td>{{ $item->limit }}</td>
                                <td>{{ $item->use_by_user }}</td>
                                <td class="small">
                                    {{ optional($item->valid_from)->format('d/m/Y H:i') }} &ndash;
                                    {{ optional($item->valid_until)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <span class="badge
                                        @if($item->status === 'active') bg-success-subtle text-success
                                        @elseif($item->status === 'inactive') bg-secondary-subtle text-secondary
                                        @elseif($item->status === 'expire') bg-warning-subtle text-warning
                                        @else bg-danger-subtle text-danger
                                        @endif">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('voucher-user.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-eye-line"></i> Show
                                        </a>
                                        <a href="{{ route('voucher-user.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-voucher-user-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus voucher ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-voucher-user-{{ $item->id }}" action="{{ route('voucher-user.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada voucher.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                 {{ $voucherUsers->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
