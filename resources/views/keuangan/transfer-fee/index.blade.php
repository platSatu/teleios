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

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1">Transfer Fee Pengajar</h4>
                <p class="text-muted mb-3">Bagi fee bulanan dari saldo Branch ke tiap pengajar, dihitung otomatis dari sesi Jadwal Kelas yang sudah berlangsung.</p>

                <form method="GET" class="row g-2 align-items-end">
                    @if($branches->count() > 1)
                        <div class="col-auto">
                            <label class="form-label mb-1">Branch</label>
                            <select name="branch_office_id" class="form-select" onchange="this.form.submit()">
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branch && $branch->id === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-auto">
                        <label class="form-label mb-1">Bulan</label>
                        <select name="month" class="form-select" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::createFromDate(2000, $m, 1)->translatedFormat('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1">Tahun</label>
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            @foreach(range(now()->year, now()->year - 3) as $y)
                                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <noscript><div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Tampilkan</button></div></noscript>
                </form>
            </div>
        </div>

        @if($branch && $preview)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Preview — {{ $branch->name }}, {{ \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y') }}</h5>
                            <p class="text-muted mb-0">
                                {{ $preview['sesi_count'] }} sesi tercatat ·
                                {{ $preview['pengajar_count'] }} pengajar ·
                                total <span class="fw-semibold">Rp {{ number_format($preview['total'], 0, ',', '.') }}</span>
                            </p>
                        </div>

                        @if($preview['total'] > 0)
                            <form method="POST" action="{{ route('keuangan.transfer-fee.execute') }}"
                                  onsubmit="return confirm('Transfer fee Rp {{ number_format($preview['total'], 0, ',', '.') }} ke {{ $preview['pengajar_count'] }} pengajar untuk periode {{ \Illuminate\Support\Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y') }}? Tindakan ini tidak bisa dibatalkan.');">
                                @csrf
                                <input type="hidden" name="branch_office_id" value="{{ $branch->id }}">
                                <input type="hidden" name="year" value="{{ $year }}">
                                <input type="hidden" name="month" value="{{ $month }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-send-plane-line"></i> Transfer Fee Sekarang
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pengajar</th>
                                    <th class="text-center">Jumlah Sesi</th>
                                    <th class="text-end">Total Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($preview['breakdown'] as $row)
                                    <tr>
                                        <td>{{ $row['pengajar_name'] }}</td>
                                        <td class="text-center">{{ $row['sesi_count'] }}</td>
                                        <td class="text-end">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Tidak ada fee yang perlu dibayarkan untuk periode ini — belum ada sesi yang tercatat hadir/tidak-hadir, atau semua sesi periode ini sudah pernah ditransfer sebelumnya.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if($branch)
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Transfer Fee — {{ $branch->name }}</h5>
                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Periode</th>
                                    <th class="text-center">Pengajar</th>
                                    <th class="text-center">Sesi</th>
                                    <th class="text-end">Total</th>
                                    <th>Dieksekusi Oleh</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($riwayat as $row)
                                    <tr>
                                        <td>{{ $row->periodeLabel() }}</td>
                                        <td class="text-center">{{ $row->pengajar_count }}</td>
                                        <td class="text-center">{{ $row->sesi_count }}</td>
                                        <td class="text-end">Rp {{ number_format((float) $row->total_debited, 0, ',', '.') }}</td>
                                        <td>{{ $row->executedBy->name ?? '-' }}</td>
                                        <td>{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat transfer fee untuk branch ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
