{{-- Label status rekening. Butuh $account. --}}
@php
    [$class, $label] = match ($account->state()) {
        'review' => ['bg-warning-subtle text-warning', 'Perlu diperiksa'],
        'scheduled' => ['bg-info-subtle text-info', 'Segera aktif'],
        'active' => ['bg-success-subtle text-success', 'Aktif'],
        'replaced' => ['bg-secondary-subtle text-secondary', 'Tidak dipakai lagi'],
        default => ['bg-danger-subtle text-danger', 'Ditolak'],
    };
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
