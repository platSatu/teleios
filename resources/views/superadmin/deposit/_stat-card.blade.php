{{-- Satu kartu ringkasan. Variabel: $label, $value, $icon, $tone (primary|success|warning|danger|info|secondary), $sub & $col (opsional). --}}
<div class="{{ $col ?? 'col-12 col-sm-6 col-xl-3' }}">
    <div class="card border-0 shadow-sm mb-0 h-100">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="avatar-item avatar-lg rounded-circle bg-{{ $tone }}-subtle text-{{ $tone }} flex-shrink-0">
                <i class="{{ $icon }} fs-4"></i>
            </div>
            <div class="min-w-0">
                <div class="text-muted small">{{ $label }}</div>
                <h4 class="mb-0 text-nowrap">{{ $value }}</h4>
                @if (! empty($sub))
                    <div class="text-muted small">{{ $sub }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
