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
                        <p class="text-muted mb-0">Jadwal kelas kursus — pengajar, murid, waktu, dan kehadiran. Baris dengan pengajar &amp; mata pelajaran yang sama digabung selnya seperti di Excel.</p>
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

                @php
                    $hasActiveFilter = request('search') || request('status') || request('jadwal_mata_pelajaran_id')
                        || request('date_filter') || request('date_from') || request('date_to');
                @endphp
                {{--
                    Filter sebelumnya pakai d-flex flex-wrap tanpa lebar
                    kolom tetap -- di layar sempit tiap field jatuh
                    sendiri-sendiri jadi satu kolom panjang ke bawah
                    (numpuk vertikal). Diganti pakai grid Bootstrap
                    (row/col) dengan col-6 sebagai default (= 2 kolom di
                    HP), lebar col-md-*/col-lg-auto di layar lebih lebar
                    supaya tetap mengalir natural mirip tampilan
                    sebelumnya di desktop. "s/d" digabung jadi
                    input-group-text nempel ke date_to (bukan span
                    lepas) supaya gak makan satu slot kolom sendiri di
                    grid 2-kolom.
                --}}
                <form method="GET" class="mb-3">
                    @if($studentId)
                        <input type="hidden" name="student_id" value="{{ $studentId }}">
                    @endif
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Cari pengajar/murid/mata pelajaran..." value="{{ request('search') }}">
                                <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <select name="jadwal_mata_pelajaran_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Mata Pelajaran / Bidang</option>
                                @foreach ($mataPelajarans as $mp)
                                    <option value="{{ $mp->id }}" @selected(request('jadwal_mata_pelajaran_id') == $mp->id)>{{ $mp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="active" @selected(request('status') == 'active')>Active</option>
                                <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <select name="date_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Tanggal</option>
                                <option value="today" @selected(request('date_filter') == 'today')>Hari Ini</option>
                                <option value="this_week" @selected(request('date_filter') == 'this_week')>Minggu Ini</option>
                                <option value="this_month" @selected(request('date_filter') == 'this_month')>Bulan Ini</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center">
                        <div class="col-12">
                            <span class="text-muted small">atau rentang tanggal:</span>
                        </div>
                        <div class="col-6 col-md-auto">
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" title="Dari tanggal">
                        </div>
                        <div class="col-6 col-md-auto">
                            <div class="input-group">
                                <span class="input-group-text">s/d</span>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" title="Sampai tanggal">
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">Terapkan</button>
                        </div>
                        @if($hasActiveFilter)
                            <div class="col-6 col-md-auto">
                                <a href="{{ route('jadwal.kelas.index', array_filter(['student_id' => $studentId])) }}" class="btn btn-light btn-sm w-100">Reset Semua Filter</a>
                            </div>
                        @endif
                    </div>
                </form>

                @php
                    // Excel-style merge: baris berurutan dengan pengajar +
                    // mata pelajaran yang SAMA digabung jadi satu sel di
                    // kolom Pengajar & Mata Pelajaran / Bidang. Query di
                    // controller sudah diurutkan supaya baris sejenis
                    // bersebelahan (lihat JadwalKelasController::index()).
                    // Data-nya sendiri tetap 1 baris = 1 pengajar + 1
                    // student, ini murni tampilan.
                    $kelasItems = $kelasList->items();
                    $groupKey = fn ($k) => $k->pengajar_id.'|'.$k->jadwal_mata_pelajaran_id;
                    $rowSpans = [];
                    $skipRows = [];
                    foreach ($kelasItems as $idx => $kelas) {
                        if (in_array($idx, $skipRows, true)) {
                            continue;
                        }
                        $span = 1;
                        for ($j = $idx + 1; $j < count($kelasItems); $j++) {
                            if ($groupKey($kelasItems[$j]) === $groupKey($kelas)) {
                                $span++;
                                $skipRows[] = $j;
                            } else {
                                break;
                            }
                        }
                        $rowSpans[$idx] = $span;
                    }
                    $emptyColspan = 8 + ($student ? 0 : 1);
                @endphp

                <div class="table-responsive">
                    <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1200px;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap" style="width: 40px;">No</th>
                                <th class="text-nowrap">Pengajar</th>
                                <th class="text-nowrap">Mata Pelajaran / Bidang</th>
                                @unless($student)
                                    <th class="text-nowrap">Murid</th>
                                @endunless
                                <th class="text-nowrap">Mulai</th>
                                <th class="text-nowrap">Selesai</th>
                                <th class="text-nowrap" style="min-width: 340px;">Kehadiran</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-end text-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kelasItems as $idx => $kelas)
                                <tr>
                                    <td>{{ $kelasList->firstItem() + $idx }}</td>
                                    @if(isset($rowSpans[$idx]))
                                        <td rowspan="{{ $rowSpans[$idx] }}" class="align-middle text-nowrap">{{ $kelas->pengajar->name ?? '-' }}</td>
                                        <td rowspan="{{ $rowSpans[$idx] }}" class="align-middle text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                                    @endif
                                    @unless($student)
                                        <td class="text-nowrap">{{ $kelas->student->name ?? '-' }}</td>
                                    @endunless
                                    <td class="text-nowrap">{{ $kelas->start_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $kelas->end_time?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        <form action="{{ route('jadwal.kelas.attendance.update', $kelas->id) }}" method="POST" class="d-flex flex-nowrap align-items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="attendance_status" class="form-select form-select-sm" style="width: 130px; flex: 0 0 auto;" onchange="this.form.submit()">
                                                <option value="" @selected(!$kelas->attendance_status)>Belum Diabsen</option>
                                                <option value="hadir" @selected($kelas->attendance_status === 'hadir')>Hadir</option>
                                                <option value="tidak_hadir" @selected($kelas->attendance_status === 'tidak_hadir')>Tidak Hadir</option>
                                            </select>
                                            <div class="input-group input-group-sm" style="width: 180px; flex: 0 0 auto;">
                                                <input type="text" name="attendance_notes" value="{{ $kelas->attendance_notes }}" class="form-control form-control-sm" placeholder="Keterangan">
                                                <button type="submit" class="btn btn-outline-secondary" title="Simpan keterangan"><i class="ri-save-line"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="badge {{ $kelas->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kelas->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
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
                                    <td colspan="{{ $emptyColspan }}" class="text-center text-muted py-4">Belum ada Jadwal Kelas. Klik "Tambah Jadwal Kelas" untuk membuat yang pertama.</td>
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
