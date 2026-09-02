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
                        <h4 class="mb-1">Laporan Jadwal</h4>
                        <p class="text-muted mb-0">Pilih rentang tanggal untuk melihat detail sesi & rekap (murid, fee, jam mengajar per pengajar), lalu export ke Excel.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('jadwal.laporan.index') }}" class="row g-2 mb-3 align-items-end">
                    <div class="col-auto">
                        <label for="laporan-dari" class="form-label small mb-1">Dari Tanggal</label>
                        <input id="laporan-dari" type="date" name="dari" class="form-control" value="{{ $dari?->format('Y-m-d') }}" max="{{ $sampai?->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-auto">
                        <label for="laporan-sampai" class="form-label small mb-1">Sampai Tanggal</label>
                        <input id="laporan-sampai" type="date" name="sampai" class="form-control" value="{{ $sampai?->format('Y-m-d') }}" min="{{ $dari?->format('Y-m-d') }}" required>
                    </div>
                    @if($branchOffices->isNotEmpty())
                        <div class="col-auto">
                            <label for="laporan-branch" class="form-label small mb-1">Branch</label>
                            <select id="laporan-branch" name="branch_office_id" class="form-select">
                                <option value="">- Semua Branch -</option>
                                @foreach($branchOffices as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchOfficeId == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
                    </div>
                    <div class="col-auto">
                        <button type="submit" formaction="{{ route('jadwal.laporan.export') }}" class="btn btn-success">
                            <i class="ri-file-excel-2-line"></i> Export Excel
                        </button>
                    </div>
                </form>

                @if($rekap)
                    <p class="text-muted">
                        Periode:
                        <strong>{{ $dari->isSameDay($sampai) ? $dari->translatedFormat('d F Y') : $dari->translatedFormat('d M Y').' - '.$sampai->translatedFormat('d M Y') }}</strong>
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Murid Aktif</div>
                                <div class="fs-4 fw-semibold">{{ $rekap['activeStudentCount'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Murid Baru</div>
                                <div class="fs-4 fw-semibold">{{ $rekap['newStudentCount'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Reschedule</div>
                                <div class="fs-4 fw-semibold">{{ $rekap['rescheduleCount'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Total Fee Company</div>
                                <div class="fs-5 fw-semibold">Rp {{ number_format($rekap['feeCompanyTotal'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small mb-4">
                        Fee &amp; jam mengajar dihitung dari sesi yang statusnya sudah ditandai Hadir/Tidak Hadir saja (sesi yang belum
                        diabsen atau berstatus Izin/Sakit belum dihitung -- fee sesi izin pindah ke sesi penggantinya).
                    </p>

                    <h5 class="mb-2">Per Pengajar</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pengajar</th>
                                    <th>Jumlah Sesi</th>
                                    <th>Total Jam Mengajar</th>
                                    <th>Fee Pengajar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rekap['perPengajar'] as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['nama'] }}</td>
                                        <td>{{ $row['jumlah_sesi'] }}</td>
                                        <td>{{ round($row['total_menit'] / 60, 1) }} jam</td>
                                        <td>Rp {{ number_format($row['fee_pengajar'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Belum ada sesi yang diabsen (Hadir/Tidak Hadir) di periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($rekap['perPengajar']->isNotEmpty())
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td>Total</td>
                                        <td>{{ $rekap['perPengajar']->sum('jumlah_sesi') }}</td>
                                        <td>{{ round($rekap['perPengajar']->sum('total_menit') / 60, 1) }} jam</td>
                                        <td>Rp {{ number_format($rekap['feePengajarTotal'], 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <h5 class="mb-2">Detail Sesi ({{ $sesi->count() }})</h5>
                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Kelas</th>
                                    <th>Kategori</th>
                                    <th>Ruangan</th>
                                    <th>Murid</th>
                                    <th>Pengajar</th>
                                    <th>Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sesi as $kelas)
                                    <tr>
                                        <td class="text-nowrap">{{ $kelas->start_time?->translatedFormat('d M Y') }}</td>
                                        <td class="text-nowrap">{{ $kelas->start_time?->format('H:i') }}–{{ $kelas->end_time?->format('H:i') }}</td>
                                        <td>{{ $kelas->mataPelajaran?->name ?? '-' }}</td>
                                        <td>{{ $kelas->kategori?->name ?? '-' }}</td>
                                        <td>{{ $kelas->ruangan?->name ?? '-' }}</td>
                                        <td>{{ $kelas->student?->name ?? '-' }}</td>
                                        <td>{{ $kelas->pengajar?->name ?? '-' }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($kelas->attendance_status) {
                                                    'hadir' => 'bg-success-subtle text-success',
                                                    'tidak_hadir' => 'bg-secondary-subtle text-secondary',
                                                    'izin' => 'bg-warning-subtle text-warning',
                                                    default => 'bg-light text-dark',
                                                };
                                                $attendanceLabel = match($kelas->attendance_status) {
                                                    'hadir' => 'Hadir',
                                                    'tidak_hadir' => 'Tidak Hadir',
                                                    'izin' => 'Izin/Sakit',
                                                    default => 'Belum Diabsen',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $attendanceLabel }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Tidak ada sesi pada rentang tanggal ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="ri-calendar-line fs-1 d-block mb-2"></i>
                        Silakan pilih tanggal terlebih dahulu untuk menampilkan laporan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
