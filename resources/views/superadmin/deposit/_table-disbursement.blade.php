<table class="table table-centered table-hover align-middle mb-0 text-nowrap">
    <thead class="table-light">
        <tr>
            <th>Tanggal</th>
            <th>Sumber</th>
            <th>Diajukan Oleh</th>
            <th class="text-end">Saldo Dipotong</th>
            <th class="text-end">Fee</th>
            <th class="text-end">Dikirim</th>
            <th>Bank Tujuan</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $item)
            <tr>
                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                <td>{{ $item->sourceLabel() }}</td>
                <td>
                    {{ $item->requestedBy->name ?? '-' }}
                    <div class="text-muted small">{{ $item->requestedBy->email ?? '' }}</div>
                </td>
                <td class="text-end">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                <td class="text-end">Rp {{ number_format((float) $item->fee_amount, 0, ',', '.') }}</td>
                <td class="text-end">Rp {{ number_format($item->transferAmount(), 0, ',', '.') }}</td>
                <td>
                    {{ $item->bank_code }} {{ $item->bank_account }}
                    <div class="text-muted small">a.n. {{ $item->account_name }}</div>
                </td>
                <td>
                    <span class="badge {{ match ($item->status) { 'success' => 'bg-success-subtle text-success', 'failed', 'rejected' => 'bg-danger-subtle text-danger', 'cancelled' => 'bg-secondary-subtle text-secondary', default => 'bg-warning-subtle text-warning' } }}">{{ $item->statusLabel() }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada disbursement.</td></tr>
        @endforelse
    </tbody>
</table>
