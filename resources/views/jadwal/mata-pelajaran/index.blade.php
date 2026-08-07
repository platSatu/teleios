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
                        <h4 class="mb-1">Mata Pelajaran</h4>
                        <p class="text-muted mb-0">Katalog mata pelajaran per cabang — tiap cabang punya daftarnya sendiri.</p>
                    </div>
                    <a href="{{ route('jadwal.mata-pelajaran.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Mata Pelajaran
                    </a>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama mata pelajaran..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    @if ($branchOffices->count() > 1)
                        <select name="branch_office_id" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
                            <option value="">Semua Cabang</option>
                            @foreach ($branchOffices as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_office_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if(request('search') || request('branch_office_id'))
                        <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Cabang</th>
                                <th>Durasi</th>
                                <th>Jumlah Jadwal Kelas</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mataPelajaran as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->name }}</td>
                                    <td>{{ $item->branchOffice->name ?? '-' }}</td>
                                    <td>{{ $item->durasi_menit ? $item->durasi_menit.' menit' : '-' }}</td>
                                    <td>{{ $item->jadwal_kelas_count }}</td>
                                    <td>
                                        <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $item->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.jadwal-kelas.create', ['branch_office_id' => $item->branch_office_id, 'mata_pelajaran_id' => $item->id]) }}" class="btn btn-sm btn-light text-primary" title="Tambah Jadwal Kelas untuk mata pelajaran ini">
                                            <i class="ri-calendar-2-line"></i> Tambah Jadwal
                                        </a>
                                        <a href="{{ route('jadwal.mata-pelajaran.edit', $item->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.mata-pelajaran.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus mata pelajaran ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada mata pelajaran. Klik "Tambah Mata Pelajaran" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $mataPelajaran->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
