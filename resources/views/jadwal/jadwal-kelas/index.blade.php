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
                        <h4 class="mb-1">Jadwal Kelas</h4>
                        <p class="text-muted mb-0">Jadwal kelas mingguan per cabang — kelola murid, sesi, dan notifikasi WA dari sini.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('jadwal.pengaturan-pesan.index') }}" class="btn btn-light" title="Atur redaksi pesan WA otomatis">
                            <i class="ri-message-3-line"></i> Pengaturan Pesan
                        </a>
                        <a href="{{ route('jadwal.jadwal-kelas.create') }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Kelas
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama kelas..." value="{{ request('search') }}">
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
                        <a href="{{ route('jadwal.jadwal-kelas.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                                <th>Cabang</th>
                                <th>Guru</th>
                                <th>Jadwal</th>
                                <th>Murid</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jadwalKelas as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->name ?: '-' }}</td>
                                    <td>{{ $item->mataPelajaran->name ?? '-' }}</td>
                                    <td>{{ $item->branchOffice->name ?? '-' }}</td>
                                    <td>
                                        @if ($item->guru)
                                            <a href="{{ route('jadwal.jadwal-kelas.guru.show', $item->guru_user_id) }}" title="Lihat semua jadwal {{ $item->guru->name }}">{{ $item->guru->name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $item->hari }}, {{ substr($item->jam_mulai, 0, 5) }}-{{ substr($item->jam_selesai, 0, 5) }}</td>
                                    <td>{{ $item->murid_count }}</td>
                                    <td>
                                        <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $item->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.jadwal-kelas.show', $item->id) }}" class="btn btn-sm btn-light" title="Kelola">
                                            <i class="ri-settings-3-line"></i>
                                        </a>
                                        <a href="{{ route('jadwal.jadwal-kelas.edit', $item->id) }}" class="btn btn-sm btn-light" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.jadwal-kelas.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal kelas ini? Semua data murid & sesi ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada jadwal kelas. Klik "Tambah Jadwal Kelas" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $jadwalKelas->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
