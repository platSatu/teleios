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
                        <h4 class="mb-1">Jadwal Rutin <span class="text-muted fs-6 fw-normal">— {{ $student->name }}</span></h4>
                        <p class="text-muted mb-0">Cetakan jadwal mingguan berulang murid ini. Satu murid boleh punya lebih dari satu baris (lintas kelas/kategori berbeda).</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('jadwal.student.index') }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Student
                        </a>
                        <a href="{{ route('jadwal.rutin.create', ['student_id' => $student->id]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Rutin
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hari</th>
                                <th>Jam</th>
                                <th>Kelas / Kategori</th>
                                <th>Pengajar</th>
                                <th>Ruangan</th>
                                <th>Efektif</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rutins as $rutin)
                                <tr>
                                    <td class="fw-semibold">{{ $rutin->hariLabel() }}</td>
                                    <td>{{ substr($rutin->jam_mulai, 0, 5) }}–{{ $rutin->jamSelesai() }}</td>
                                    <td>{{ $rutin->kategori?->mataPelajaran?->name }} — {{ $rutin->kategori?->name }}</td>
                                    <td>{{ $rutin->pengajar?->name }}</td>
                                    <td>{{ $rutin->ruangan?->name ?? '-' }}</td>
                                    <td class="text-muted">
                                        {{ $rutin->efektif_mulai?->format('d/m/Y') }}
                                        @if($rutin->efektif_selesai)
                                            – {{ $rutin->efektif_selesai->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $rutin->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $rutin->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.rutin.edit', $rutin->id) }}" class="btn btn-sm btn-light"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('jadwal.rutin.destroy', $rutin->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Rutin ini? Sesi yang sudah pernah digenerate TIDAK ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada Jadwal Rutin untuk murid ini.</td>
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
