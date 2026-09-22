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
                        <h4 class="mb-1">Pelanggan Tagihan</h4>
                        <p class="text-muted mb-0">Kontak yang ditagih lewat fitur Tagihan.</p>
                    </div>
                    <a href="{{ route('tagihan.pelanggan.create', array_filter(['branch_office_id' => $branchOfficeId])) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Pelanggan
                    </a>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama/telepon..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Telepon</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pelanggan as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row->name }}</td>
                                    <td>{{ $row->phone_number ?: '-' }}</td>
                                    <td>{{ $row->branchOffice->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $row->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $row->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('tagihan.pelanggan.edit', $row->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('tagihan.pelanggan.destroy', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pelanggan ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada pelanggan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $pelanggan->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
