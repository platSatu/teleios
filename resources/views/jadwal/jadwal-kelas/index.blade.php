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

        @if($student)
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.pengajar.index', ['jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id]) }}">Pengajar</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.student.index', ['jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id, 'pengajar_id' => $student->pengajar_id]) }}">{{ $student->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Jadwal Kelas</li>
                </ol>
            </nav>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Jadwal Kelas{{ $student ? ' — '.$student->name : '' }}</h4>
                        <p class="text-muted mb-0">Jadwal kelas kursus — pengajar, murid, dan waktu pelaksanaannya.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($student)
                            <a href="{{ route('jadwal.student.index', ['jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id, 'pengajar_id' => $student->pengajar_id]) }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Student
                            </a>
                        @endif
                        <a href="{{ route('jadwal.kelas.create', $student ? [
                            'branch_office_id' => $student->branch_office_id,
                            'jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id,
                            'pengajar_id' => $student->pengajar_id,
                            'student_id' => $student->id,
                        ] : []) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Kelas
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($studentId)
                        <input type="hidden" name="student_id" value="{{ $studentId }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari pengajar/murid/mata pelajaran..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="jadwal_mata_pelajaran_id" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
                        <option value="">Semua Mata Pelajaran / Bidang</option>
                        @foreach ($mataPelajarans as $mp)
                            <option value="{{ $mp->id }}" @selected(request('jadwal_mata_pelajaran_id') == $mp->id)>{{ $mp->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status') || request('jadwal_mata_pelajaran_id'))
                        <a href="{{ route('jadwal.kelas.index', array_filter(['student_id' => $studentId])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mata Pelajaran / Bidang</th>
                                <th>Pengajar</th>
                                @unless($student)
                                    <th>Murid</th>
                                @endunless
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kelasList as $kelas)
                                <tr>
                                    <td>{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                                    <td>{{ $kelas->pengajar->name ?? '-' }}</td>
                                    @unless($student)
                                        <td>{{ $kelas->student->name ?? '-' }}</td>
                                    @endunless
                                    <td>{{ $kelas->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $kelas->end_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $kelas->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kelas->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('jadwal.kelas.edit', $kelas->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $student ? 6 : 7 }}" class="text-center text-muted py-4">Belum ada Jadwal Kelas. Klik "Tambah Jadwal Kelas" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $kelasList->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
