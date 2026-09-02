@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Laporan Harian</h4>
                        <p class="text-muted mb-0">Kelas/kategori yang terpakai, ruangan, murid, pengajar, dan jamnya untuk satu tanggal.</p>
                    </div>
                    <a href="{{ route('jadwal.laporan.harian.export', array_filter(['tanggal' => $date->format('Y-m-d'), 'branch_office_id' => $branchOfficeId])) }}" class="btn btn-success">
                        <i class="ri-file-excel-2-line"></i> Export Excel
                    </a>
                </div>

                <form method="GET" class="row g-2 mb-3 align-items-center">
                    <div class="col-auto">
                        <input type="date" name="tanggal" class="form-control" value="{{ $date->format('Y-m-d') }}">
                    </div>
                    @if($branchOffices->isNotEmpty())
                        <div class="col-auto">
                            <select name="branch_office_id" class="form-select">
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
                </form>

                <p class="text-muted">Total sesi tanggal {{ $date->translatedFormat('d F Y') }}: <strong>{{ $sesi->count() }}</strong></p>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
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
                                    <td colspan="7" class="text-center text-muted py-4">Tidak ada sesi pada tanggal ini.</td>
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
