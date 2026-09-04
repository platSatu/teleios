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
                                <th>Kategori</th>
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
                                    <td>
                                        {{-- Update 4 September 2026: "Kategori" DI-DERIVE dari Jadwal
                                        Rutin aktif murid ini (lihat JadwalStudentController::index()),
                                        BUKAN field tersimpan langsung -- kalau murid belum punya Jadwal
                                        Rutin aktif sama sekali, belum ada Kategori yang bisa ditentukan. --}}
                                        @forelse($student->kategori_names as $kategoriName)
                                            <span class="badge bg-light text-dark border fw-normal">{{ $kategoriName }}</span>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
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
                                        {{-- Update 4 September 2026 (permintaan user): tombol "Jadwal
                                        Rutin" & "Add Jadwal" DIHAPUS dari sini -- keduanya jadi jalan
                                        pintas yang bisa dipakai bikin jadwal murid tanpa lewat checklist
                                        ketersediaan Pengajar di halaman Edit (lihat
                                        JadwalStudentController::pengajarSlotsPanel()). Admin yang mau
                                        kelola Jadwal Rutin/Jadwal Kelas tetap bisa lewat menu
                                        sidebar-nya sendiri -- cuma shortcut per-baris ini yang hilang,
                                        Aksi di sini disederhanakan jadi Edit + Delete saja, sama
                                        seperti pola baris Pengajar (lihat jadwal-pengajar/index.blade.php). --}}
                                        <a href="{{ route('jadwal.student.edit', $student->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        {{-- Update 4 September 2026 (permintaan user, laporan "fungsi
                                        delete di table student tidak berfungsi"): tombol Hapus lama
                                        SELALU gagal kalau murid sudah punya sesi Jadwal Kelas (FK
                                        restrictOnDelete, lihat migration perbaikannya). Sekarang ada 2
                                        aksi terpisah: "Nonaktifkan" (aman, status=inactive, riwayat
                                        jadwal & fee tetap tersimpan tapi tidak lagi ikut dihitung di
                                        laporan -- lihat JadwalStudentController::deactivate()) dan
                                        "Hapus Total" (permanen, ikut menghapus SELURUH riwayat jadwal &
                                        fee-nya, tidak bisa dibatalkan). Tombol Nonaktifkan hanya
                                        muncul kalau murid masih aktif -- tidak ada gunanya nonaktifkan
                                        murid yang sudah inactive. --}}
                                        @if($student->status === 'active')
                                            <form action="{{ route('jadwal.student.deactivate', $student->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan Student ini? Riwayat jadwal & fee-nya tetap tersimpan, tapi tidak lagi ikut dihitung di laporan.');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-light text-warning" title="Nonaktifkan"><i class="ri-pause-circle-line"></i></button>
                                            </form>
                                        @endif
                                        <form action="{{ route('jadwal.student.destroy', $student->id) }}" method="POST" class="d-inline" onsubmit="return confirm('HAPUS TOTAL Student ini beserta SELURUH riwayat jadwal & fee-nya? Tindakan ini TIDAK BISA DIBATALKAN. Kalau cuma ingin murid ini tidak aktif lagi tapi datanya tetap tersimpan, gunakan tombol Nonaktifkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus Total"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 6 + ($mataPelajaran ? 0 : 1) + ($pengajar ? 0 : 1) }}" class="text-center text-muted py-4">Belum ada Student. Klik "Tambah Student" untuk membuat yang pertama.</td>
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
