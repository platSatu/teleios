<table class="table table-centered table-hover align-middle mb-0 text-nowrap">
    <thead class="table-light">
        <tr>
            <th>Tanggal</th>
            <th>User</th>
            <th>Paket</th>
            <th class="text-end">Nominal</th>
            <th>Masa Aktif</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $item)
            <tr>
                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                <td>
                    {{ $item->user->name ?? '-' }}
                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                </td>
                <td>{{ $item->package->name ?? '-' }}</td>
                <td class="text-end">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                <td>{{ $item->start_date?->format('d M Y') ?? '-' }} – {{ $item->end_date?->format('d M Y') ?? '-' }}</td>
                <td>
                    <span class="badge {{ match ($item->status) { 'ACTIVE' => 'bg-success-subtle text-success', 'CANCELLED' => 'bg-danger-subtle text-danger', default => 'bg-secondary-subtle text-secondary' } }}">{{ $item->status }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penjualan paket.</td></tr>
        @endforelse
    </tbody>
</table>
