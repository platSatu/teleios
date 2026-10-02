{{-- Kolom sama dengan superadmin/referral-code/history. --}}
<table class="table table-centered table-hover align-middle mb-0 text-nowrap">
    <thead class="table-light">
        <tr>
            <th>Kode Referral</th>
            <th>Pemilik Kode (Referrer)</th>
            <th>Dipakai Oleh</th>
            <th>Package Dibeli</th>
            <th>Rate</th>
            <th class="text-end">Diskon Customer</th>
            <th class="text-end">Komisi</th>
            <th>Status</th>
            <th>Waktu</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $item)
            <tr>
                <td><code>{{ $item->referralCode->code ?? '-' }}</code></td>
                <td>
                    {{ $item->referralCode->user->name ?? '-' }}
                    <div class="text-muted small">{{ $item->referralCode->user->email ?? '' }}</div>
                </td>
                <td>
                    {{ $item->usedBy->name ?? '-' }}
                    <div class="text-muted small">{{ $item->usedBy->email ?? '' }}</div>
                </td>
                <td>{{ $item->subscription?->package?->name ?? '-' }}</td>
                <td>{{ rtrim(rtrim(number_format((float) $item->discount_percent, 2, '.', ''), '0'), '.') }}%</td>
                <td class="text-end">Rp {{ number_format((float) $item->buyer_discount_amount, 0, ',', '.') }}</td>
                <td class="text-end fw-semibold {{ $item->commission_amount > 0 ? 'text-success' : 'text-muted' }}">Rp {{ number_format((float) $item->commission_amount, 0, ',', '.') }}</td>
                <td>@include('superadmin.referral-code._status', ['cancellable' => true])</td>
                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada pemakaian referral.</td></tr>
        @endforelse
    </tbody>
</table>
