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

        @if($mataPelajaran)
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                    @if($kategoriId ?? null)
                        <li class="breadcrumb-item"><a href="{{ route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategoriId]) }}">{{ $mataPelajaran->name }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $mataPelajaran->name }}</li>
                    @endif
                    @if($pengajar)
                        <li class="breadcrumb-item active" aria-current="page">{{ $pengajar->name }}</li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">Student</li>
                </ol>
            </nav>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Student{{ $pengajar ? ' — '.$pengajar->name : '' }}</h4>
                        <p class="text-muted mb-0">Daftar murid. Sesuai company/branch Anda — bukan seluruh user.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($kategoriId ?? null)
                            <a href="{{ route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategoriId]) }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Pengajar
                            </a>
                        @elseif($mataPelajaran)
                            <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Mata Pelajaran / Bidang
                            </a>
                        @endif
                        <a href="{{ route('jadwal.student.create', array_filter(['jadwal_mata_pelajaran_id' => $mataPelajaranId, 'pengajar_id' => $pengajarId, 'jadwal_kategori_id' => $kategoriId ?? null])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Student
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($mataPelajaranId)
                        <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaranId }}">
                    @endif
                    @if($pengajarId)
                        <input type="hidden" name="pengajar_id" value="{{ $pengajarId }}">
                    @endif
                    @if($kategoriId ?? null)
                        <input type="hidden" name="jadwal_kategori_id" value="{{ $kategoriId }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama student..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status'))
                        <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $mataPelajaranId, 'pengajar_id' => $pengajarId, 'jadwal_kategori_id' => $kategoriId ?? null])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                @unless($mataPelajaran)
                                    <th>Mata Pelajaran / Bidang</th>
                                @endunless
                                @unless($pengajar)
                                    <th>Pengajar</th>
                                @endunless
                                <th>Branch</th>
                                <th>No. HP</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                                <tr>
                                    <td class="fw-semibold">{{ $student->name }}</td>
                                    @unless($mataPelajaran)
                                        <td>{{ $student->mataPelajaran->name ?? '-' }}</td>
                                    @endunless
                                    @unless($pengajar)
                                        <td>{{ $student->pengajar->name ?? '-' }}</td>
                                    @endunless
                                    <td>{{ $student->branchOffice->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        @if($student->parent_phone_number || $student->student_phone_number)
                                            <span title="{{ $student->parent_phone_number ? 'Orang tua' : 'Murid' }}">{{ $student->parent_phone_number ?: $student->student_phone_number }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $student->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $student->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.rutin.index', ['student_id' => $student->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-repeat-line"></i> Jadwal Rutin
                                        </a>
                                        <a href="{{ route('jadwal.kelas.create', [
                                            'branch_office_id' => $student->branch_office_id,
                                            'jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id,
                                            'pengajar_id' => $student->pengajar_id,
                                            'student_id' => $student->id,
                                        ]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Jadwal
                                        </a>
                                        <a href="{{ route('jadwal.student.edit', $student->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.student.destroy', $student->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Student ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 5 + ($mataPelajaran ? 0 : 1) + ($pengajar ? 0 : 1) }}" class="text-center text-muted py-4">Belum ada Student. Klik "Tambah Student" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $students->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
