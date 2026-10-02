<table class="table table-centered table-hover align-middle mb-0 text-nowrap">
    <thead class="table-light">
        <tr>
            <th>Referensi</th>
            <th>User</th>
            <th class="text-end">Nominal</th>
            <th>Metode</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th class="text-end">Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $item)
            <tr>
                <td class="fw-semibold">{{ $item->reference_number }}</td>
                <td>
                    {{ $item->user->name ?? '-' }}
                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                </td>
                <td class="text-end">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                <td>{{ $item->payment_method ?? '-' }}</td>
                <td>
                    <span class="badge {{ match ($item->status) { 'SUCCESS' => 'bg-success-subtle text-success', 'PENDING' => 'bg-warning-subtle text-warning', default => 'bg-danger-subtle text-danger' } }}">{{ $item->status }}</span>
                </td>
                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                <td class="text-end">
                    <a href="{{ route('deposits.show', $item->id) }}" class="btn btn-outline-secondary btn-sm"><i class="ri-eye-line"></i> Detail</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada deposit.</td></tr>
        @endforelse
    </tbody>
</table>
