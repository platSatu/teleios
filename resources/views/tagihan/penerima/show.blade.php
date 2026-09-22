@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ $penerima->pelanggan->name ?? '-' }}</h4>
                        <p class="text-muted mb-0">{{ $penerima->tagihan->name ?? '-' }} &middot; {{ $penerima->tagihan->category->name ?? '-' }}</p>
                    </div>
                    <a href="{{ route('tagihan.laporan.index') }}" class="btn btn-light">Kembali</a>
                </div>

                <table class="table table-sm table-borderless mb-3">
                    <tr>
                        <td class="text-muted" style="width: 30%">Nominal</td>
                        <td class="fw-semibold">Rp {{ number_format($penerima->amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Denda</td>
                        <td>Rp {{ number_format($penerima->denda_amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>{{ str_replace('_', ' ', $penerima->status) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Dibayar</td>
                        <td>{{ $penerima->paid_at?->format('d M Y H:i') ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Link Publik</td>
                        <td>
                            @if($penerima->branchOffice?->slug)
                                <a href="{{ route('tagihan.public.show', ['branchSlug' => $penerima->branchOffice->slug, 'token' => $penerima->public_token]) }}" target="_blank">Buka <i class="ri-external-link-line"></i></a>
                            @endif
                        </td>
                    </tr>
                </table>

                <div class="d-flex gap-2">
                    @if($penerima->status === 'belum_bayar')
                        <form action="{{ route('tagihan.laporan.cancel', $penerima->id) }}" method="POST" onsubmit="return confirm('Batalkan invoice ini?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">Batalkan Invoice</button>
                        </form>
                    @endif
                    <form action="{{ route('tagihan.laporan.regenerate-token', $penerima->id) }}" method="POST" onsubmit="return confirm('Ganti link pembayaran? Link lama akan berhenti berfungsi.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Regenerate Link</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Riwayat Transaksi Duitku</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-centered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Status</th>
                                <th>Nominal</th>
                                <th>Metode</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($penerima->paymentTransactions as $tx)
                                <tr>
                                    <td>{{ $tx->created_at?->format('d M Y H:i') }}</td>
                                    <td>{{ $tx->status }}</td>
                                    <td>Rp {{ number_format($tx->amount, 0, ',', '.') }}</td>
                                    <td>{{ $tx->payment_method ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada percobaan pembayaran.</td>
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
