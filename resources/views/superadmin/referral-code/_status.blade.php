{{-- Status komisi satu ReferralCodeUsage. Tombol batal (komisi tertahan) hanya kalau $cancellable -- superadmin. --}}
@php
    $badge = match ($item->status) {
        'pending' => ['bg-warning-subtle text-warning', 'Tertahan s/d '.$item->available_at?->format('d M Y')],
        'cancelled' => ['bg-danger-subtle text-danger', 'Dibatalkan'],
        default => ['bg-success-subtle text-success', $item->commission_amount > 0 ? 'Cair' : 'Tanpa komisi'],
    };
@endphp
<span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
@if ($item->status === 'cancelled' && $item->cancel_reason)
    <div class="text-muted small">{{ $item->cancel_reason }}</div>
@endif
@if ($item->status === 'pending' && ($cancellable ?? false))
    <form action="{{ route('referral-code.usage.cancel', $item->id) }}" method="POST" class="mt-1"
        onsubmit="var r = prompt('Alasan pembatalan komisi (mis. komplain / refund):'); if (!r) return false; this.cancel_reason.value = r; return true;">
        @csrf
        <input type="hidden" name="cancel_reason">
        <button type="submit" class="btn btn-link btn-sm text-danger p-0">Batalkan komisi</button>
    </form>
@endif
