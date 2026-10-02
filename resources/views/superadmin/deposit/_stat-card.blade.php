{{--
    Satu kartu ringkasan (ringkas: font & ikon kecil supaya satu bagian muat 1 baris).
    Variabel: $label, $value, $icon, $tone (primary|success|warning|danger|info|secondary), $sub (opsional).
--}}
<div class="col-12 col-sm-6 col-lg-4 col-xl">
    <div class="card border-0 shadow-sm mb-0 h-100">
        <div class="card-body d-flex align-items-center gap-2 py-3 px-3">
            <div class="avatar-item avatar-md rounded-circle bg-{{ $tone }}-subtle text-{{ $tone }} flex-shrink-0">
                <i class="{{ $icon }} fs-5"></i>
            </div>
            <div class="min-w-0">
                <div class="text-muted small text-nowrap">{{ $label }}</div>
                <div class="fs-5 fw-semibold text-nowrap lh-sm">{{ $value }}</div>
                @if (! empty($sub))
                    <div class="text-muted small text-nowrap">{{ $sub }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
