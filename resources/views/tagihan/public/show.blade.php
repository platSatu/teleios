<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Sama alasan dengan resources/views/form/public/show.blade.php --
         brand publik "Konexa" di-hardcode, bukan config('app.name'). --}}
    <title>Konexa | Tagihan {{ $penerima->tagihan->name ?? '' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #673ab7;
            --brand-dark: #4d2c91;
            --brand-soft: #f3edfb;
            --bg: #eef0f7;
            --border: #e6e1f5;
            --text: #1b1b23;
            --muted: #6b6f80;
            --danger: #d93025;
            --success: #188038;
            --warning: #b8860b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            line-height: 1.55;
        }
        .tp-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .tp-card { width: 100%; max-width: 480px; background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(103,58,183,.10); overflow: hidden; }
        .tp-head { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #fff; padding: 28px 28px 22px; }
        .tp-head .brand { font-weight: 700; letter-spacing: .02em; opacity: .85; font-size: 13px; text-transform: uppercase; }
        .tp-head h1 { font-size: 20px; margin: 8px 0 0; font-weight: 700; }
        .tp-body { padding: 24px 28px 28px; }
        .tp-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
        .tp-row:last-child { border-bottom: none; }
        .tp-row .label { color: var(--muted); }
        .tp-row .value { font-weight: 600; }
        .tp-total { display: flex; justify-content: space-between; align-items: baseline; margin-top: 18px; padding-top: 18px; border-top: 2px solid var(--brand-soft); }
        .tp-total .label { font-size: 14px; color: var(--muted); }
        .tp-total .value { font-size: 26px; font-weight: 800; color: var(--brand-dark); }
        .tp-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .tp-badge.warning { background: #fff6df; color: var(--warning); }
        .tp-badge.success { background: #e6f4ea; color: var(--success); }
        .tp-badge.danger { background: #fdecea; color: var(--danger); }
        .tp-badge.secondary { background: #eee; color: var(--muted); }
        .tp-btn { display: block; width: 100%; text-align: center; padding: 14px; border-radius: 12px; border: none; background: var(--brand); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 22px; text-decoration: none; }
        .tp-btn:disabled { background: #c9c3e0; cursor: not-allowed; }
        .tp-note { margin-top: 12px; font-size: 12px; color: var(--muted); text-align: center; }
        .tp-alert { margin-bottom: 18px; padding: 12px 14px; border-radius: 10px; font-size: 13px; }
        .tp-alert.success { background: #e6f4ea; color: var(--success); }
        .tp-alert.danger { background: #fdecea; color: var(--danger); }
        .tp-alert.info { background: var(--brand-soft); color: var(--brand-dark); }
        .tp-footer { text-align: center; padding: 16px; font-size: 12px; color: var(--muted); }
        .tp-footer a { color: var(--brand); text-decoration: none; }
    </style>
</head>
<body>
    <div class="tp-page">
        <div class="tp-card">
            <div class="tp-head">
                {{-- Brand "Konexa" di bagian atas dihilangkan, diganti
                     nomor invoice (App\Models\TagihanPenerima::invoice_number,
                     format "{prefix category}-XXXXXX") -- 23 September
                     2026 permintaan user. --}}
                <div class="brand">{{ $penerima->invoice_number ?? '-' }}</div>
                <h1>{{ $penerima->tagihan->name ?? 'Tagihan' }}</h1>
            </div>
            <div class="tp-body">
                @isset($returnMessage)
                    <div class="tp-alert {{ $returnMessage['type'] }}">{{ $returnMessage['text'] }}</div>
                @endisset

                <div class="tp-row">
                    <span class="label">Kategori</span>
                    <span class="value">{{ $penerima->tagihan->category->name ?? '-' }}</span>
                </div>
                <div class="tp-row">
                    <span class="label">Ditagihkan Kepada</span>
                    <span class="value">{{ $penerima->pelanggan->name ?? '-' }}</span>
                </div>
                <div class="tp-row">
                    <span class="label">Jatuh Tempo</span>
                    <span class="value">{{ $penerima->tagihan?->due_date?->format('d M Y') ?? '-' }}</span>
                </div>
                <div class="tp-row">
                    <span class="label">Nominal</span>
                    <span class="value">Rp {{ number_format($penerima->amount, 0, ',', '.') }}</span>
                </div>
                @if((float) $dendaSaatIni > 0)
                    <div class="tp-row">
                        <span class="label">Denda Keterlambatan</span>
                        <span class="value">Rp {{ number_format($dendaSaatIni, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="tp-row">
                    <span class="label">Status</span>
                    <span>
                        @php
                            $badgeClass = [
                                'belum_bayar' => 'warning',
                                'lunas' => 'success',
                                'kadaluarsa' => 'secondary',
                                'dibatalkan' => 'danger',
                            ][$penerima->status] ?? 'secondary';
                            $badgeText = [
                                'belum_bayar' => 'Belum Bayar',
                                'lunas' => 'Lunas',
                                'kadaluarsa' => 'Kadaluarsa',
                                'dibatalkan' => 'Dibatalkan',
                            ][$penerima->status] ?? $penerima->status;
                        @endphp
                        <span class="tp-badge {{ $badgeClass }}">{{ $badgeText }}</span>
                    </span>
                </div>

                <div class="tp-total">
                    <span class="label">Total Bayar</span>
                    <span class="value">Rp {{ number_format((float) $penerima->amount + (float) $dendaSaatIni, 0, ',', '.') }}</span>
                </div>

                @if($penerima->status === 'belum_bayar')
                    <form action="{{ route('tagihan.public.checkout', ['branchSlug' => $penerima->branchOffice->slug, 'token' => $penerima->public_token]) }}" method="POST">
                        @csrf
                        <button type="submit" class="tp-btn">Bayar Sekarang</button>
                    </form>
                    <p class="tp-note">Anda akan diarahkan ke Duitku untuk memilih metode pembayaran (Virtual Account, e-wallet, QRIS, dll).</p>
                @endif
            </div>
        </div>
    </div>
    <div class="tp-footer">
        Diproses oleh <a href="https://konexa.id" target="_blank" rel="noopener">Konexa</a>
    </div>
</body>
</html>
