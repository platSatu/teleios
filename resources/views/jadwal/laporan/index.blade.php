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
                        <p class="text-muted mb-0">Pilih rentang tanggal untuk melihat ringkasan murid & omset, absensi per murid, dan fee per pengajar, lalu export ke Excel.</p>
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

                @if($laporan)
                    @php
                        $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
                        $jam = fn ($m) => \App\Services\Jadwal\JadwalLaporanService::formatJam((int) $m);
                    @endphp

                    <p class="text-muted">
                        Periode:
                        <strong>{{ $dari->isSameDay($sampai) ? $dari->translatedFormat('d F Y') : $dari->translatedFormat('d M Y').' - '.$sampai->translatedFormat('d M Y') }}</strong>
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center gap-2 text-muted small"><i class="ri-group-line fs-5 text-primary"></i> Total Murid</div>
                                <div class="fs-3 fw-semibold mt-1">{{ number_format($laporan['totalMurid'], 0, ',', '.') }}</div>
                                <div class="text-muted small">murid aktif</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center gap-2 text-muted small"><i class="ri-user-add-line fs-5 text-success"></i> Murid Baru</div>
                                <div class="fs-3 fw-semibold mt-1">{{ number_format($laporan['muridBaru'], 0, ',', '.') }}</div>
                                <div class="text-muted small">terdaftar di periode ini</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center gap-2 text-muted small"><i class="ri-book-2-line fs-5 text-info"></i> Mata Pelajaran / Bidang</div>
                                <div class="fs-3 fw-semibold mt-1">{{ number_format($laporan['mataPelajaranCount'], 0, ',', '.') }}</div>
                                <div class="text-muted small">{{ $laporan['kategoriCount'] }} kategori &middot; {{ $laporan['gradeCount'] }} grade</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center gap-2 text-muted small"><i class="ri-money-dollar-circle-line fs-5 text-warning"></i> Omset / Bulan</div>
                                <div class="fs-4 fw-semibold mt-1">{{ $rp($laporan['omset']) }}</div>
                                <div class="text-muted small">murid aktif &times; harga Grade</div>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills gap-2 mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2" data-bs-toggle="pill" data-bs-target="#laporan-tab-student" type="button" role="tab">
                                <i class="ri-user-line"></i> Student
                                <span class="badge rounded-pill bg-light text-dark">{{ $laporan['students']->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" data-bs-toggle="pill" data-bs-target="#laporan-tab-pengajar" type="button" role="tab">
                                <i class="ri-user-star-line"></i> Pengajar
                                <span class="badge rounded-pill bg-light text-dark">{{ $laporan['pengajar']->count() }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="laporan-tab-student" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-centered table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Murid</th>
                                            <th>Pengajar</th>
                                            <th>Ruangan</th>
                                            <th>Kelas</th>
                                            <th>Kategori</th>
                                            <th>Grade</th>
                                            <th class="text-center">Hadir</th>
                                            <th class="text-center">Tidak Hadir</th>
                                            <th class="text-center">Izin</th>
                                            <th class="text-center">Belum Diabsen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($laporan['students'] as $row)
                                            <tr>
                                                <td class="fw-semibold">{{ $row['murid'] }}</td>
                                                <td>{{ $row['pengajar'] }}</td>
                                                <td>{{ $row['ruangan'] }}</td>
                                                <td>{{ $row['kelas'] }}</td>
                                                <td>{{ $row['kategori'] }}</td>
                                                <td>{{ $row['grade'] }}</td>
                                                <td class="text-center"><span class="badge bg-success-subtle text-success">{{ $row['hadir'] }}</span></td>
                                                <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">{{ $row['tidak_hadir'] }}</span></td>
                                                <td class="text-center"><span class="badge bg-warning-subtle text-warning">{{ $row['izin'] }}</span></td>
                                                <td class="text-center text-muted">{{ $row['belum'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">Tidak ada sesi murid aktif pada rentang tanggal ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Izin sudah termasuk sakit. Hanya murid yang masih aktif yang dihitung.</p>
                        </div>

                        <div class="tab-pane fade" id="laporan-tab-pengajar" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-centered table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Pengajar</th>
                                            <th>Ruangan</th>
                                            <th>Kelas</th>
                                            <th>Kategori</th>
                                            <th>Grade</th>
                                            <th class="text-center">Jumlah Sesi</th>
                                            <th>Total Jam Mengajar</th>
                                            <th class="text-end">Fee Pengajar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($laporan['pengajar'] as $row)
                                            <tr>
                                                <td class="fw-semibold">{{ $row['pengajar'] }}</td>
                                                <td>{{ $row['ruangan'] }}</td>
                                                <td>{{ $row['kelas'] }}</td>
                                                <td>{{ $row['kategori'] }}</td>
                                                <td>{{ $row['grade'] }}</td>
                                                <td class="text-center">{{ $row['jumlah_sesi'] }}</td>
                                                <td>{{ $jam($row['total_menit']) }}</td>
                                                <td class="text-end">{{ $rp($row['fee_pengajar']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Belum ada sesi yang diabsen Hadir/Tidak Hadir di periode ini.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if($laporan['pengajar']->isNotEmpty())
                                        <tfoot>
                                            <tr class="fw-semibold">
                                                <td colspan="5">Total</td>
                                                <td class="text-center">{{ $laporan['pengajar']->sum('jumlah_sesi') }}</td>
                                                <td>{{ $jam($laporan['pengajar']->sum('total_menit')) }}</td>
                                                <td class="text-end">{{ $rp($laporan['pengajar']->sum('fee_pengajar')) }}</td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Dihitung dari sesi Hadir &amp; Tidak Hadir (pengajar tetap dibayar). Sesi Izin dibayar lewat sesi penggantinya. Fee = harga per sesi Grade &times; % pengajar.</p>
                        </div>
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
