@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Laporan Bulanan</h4>
                        <p class="text-muted mb-0">Murid aktif/baru, jumlah reschedule, total fee, dan lama mengajar per pengajar.</p>
                    </div>
                    <a href="{{ route('jadwal.laporan.bulanan.export', array_filter(['bulan' => $monthValue, 'branch_office_id' => $branchOfficeId])) }}" class="btn btn-success">
                        <i class="ri-file-excel-2-line"></i> Export Excel
                    </a>
                </div>

                <form method="GET" class="row g-2 mb-4 align-items-center">
                    <div class="col-auto">
                        <input type="month" name="bulan" class="form-control" value="{{ $monthValue }}">
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

                <p class="text-muted">Periode: <strong>{{ $monthLabel }}</strong></p>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Murid Aktif</div>
                            <div class="fs-4 fw-semibold">{{ $data['activeStudentCount'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Murid Baru</div>
                            <div class="fs-4 fw-semibold">{{ $data['newStudentCount'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Reschedule</div>
                            <div class="fs-4 fw-semibold">{{ $data['rescheduleCount'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Total Fee Company</div>
                            <div class="fs-5 fw-semibold">Rp {{ number_format($data['feeCompanyTotal'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                <p class="text-muted small mb-2">
                    Fee &amp; jam mengajar dihitung dari sesi yang statusnya sudah ditandai Hadir/Tidak Hadir saja (sesi yang belum
                    diabsen atau berstatus Izin/Sakit belum dihitung -- fee sesi izin pindah ke sesi penggantinya).
                </p>

                <h5 class="mb-2">Per Pengajar</h5>
                <div class="table-responsive">
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
                            @forelse($data['perPengajar'] as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['nama'] }}</td>
                                    <td>{{ $row['jumlah_sesi'] }}</td>
                                    <td>{{ round($row['total_menit'] / 60, 1) }} jam</td>
                                    <td>Rp {{ number_format($row['fee_pengajar'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada sesi yang diabsen (Hadir/Tidak Hadir) di bulan ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($data['perPengajar']->isNotEmpty())
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td>Total</td>
                                    <td>{{ $data['perPengajar']->sum('jumlah_sesi') }}</td>
                                    <td>{{ round($data['perPengajar']->sum('total_menit') / 60, 1) }} jam</td>
                                    <td>Rp {{ number_format($data['feePengajarTotal'], 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
