<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pembayaran {{ $merchant->name }}</title>
    <style>
        :root { --brand:#1f6feb; --text:#1b1b23; --muted:#6b6f80; --border:#e6e8ef; --bg:#f3f5fa; --ok:#188038; --bad:#d93025; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; color:var(--text); background:var(--bg); }
        body.embed { background:transparent; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; }
        .card { width:100%; max-width:420px; background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(20,30,60,.12); overflow:hidden; }
        .head { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .head img { width:40px; height:40px; object-fit:contain; border-radius:8px; }
        .head .name { font-weight:700; }
        .head .desc { color:var(--muted); font-size:13px; }
        .body { padding:18px 20px 22px; }
        .amount { font-size:26px; font-weight:800; margin:2px 0 14px; }
        .label { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        .methods { display:grid; gap:8px; }
        .method { display:flex; justify-content:space-between; align-items:center; width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:#fff; cursor:pointer; font-size:15px; text-align:left; }
        .method:hover { border-color:var(--brand); }
        .method small { color:var(--muted); }
        .group { margin:12px 0 6px; font-weight:600; font-size:13px; color:var(--muted); }
        .box { border:1px dashed var(--border); border-radius:12px; padding:16px; text-align:center; margin-top:8px; }
        .va { font-family:ui-monospace,Menlo,monospace; font-size:22px; font-weight:700; letter-spacing:.06em; word-break:break-all; }
        .btn { display:inline-block; padding:10px 16px; border-radius:10px; border:0; background:var(--brand); color:#fff; font-weight:600; text-decoration:none; cursor:pointer; font-size:14px; }
        .btn.light { background:#eef2f9; color:var(--text); }
        .muted { color:var(--muted); font-size:13px; }
        .center { text-align:center; }
        .ok { color:var(--ok); } .bad { color:var(--bad); }
        .err { background:#fdecea; color:var(--bad); padding:10px 12px; border-radius:10px; font-size:13px; margin-top:10px; display:none; }
        .close { position:absolute; top:10px; right:12px; background:none; border:0; font-size:22px; color:var(--muted); cursor:pointer; display:none; }
        body.embed .close { display:block; }
        .foot { padding:10px 20px 14px; color:var(--muted); font-size:11px; text-align:center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" style="position:relative">
        <button class="close" type="button" onclick="pgPost('close')" aria-label="Tutup">&times;</button>
        <div class="head">
            @if($merchant->logo_url)<img src="{{ $merchant->logo_url }}" alt="">@endif
            <div>
                <div class="name">{{ $merchant->name }}</div>
                <div class="desc">{{ $invoice->description }}</div>
            </div>
        </div>
        <div class="body">
            <div class="label">Total pembayaran</div>
            <div class="amount">Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</div>
            <div id="pgView"></div>
            <div class="err" id="pgErr"></div>
        </div>
        <div class="foot">Pembayaran diproses aman oleh teleios &middot; Duitku</div>
    </div>
</div>

@php
    $pgConfig = [
        'state' => $state,
        'methods' => $methods->map(fn ($m) => ['code' => $m->payment_method, 'name' => $m->name, 'type' => $m->type])->values(),
        'types' => \App\Models\PgFee::TYPES,
        'merchant' => $merchant->name,
        'urls' => ['method' => route('pg.checkout.method', $invoice->token), 'status' => route('pg.checkout.status', $invoice->token)],
    ];
@endphp
<script>
(function () {
    var cfg = @json($pgConfig);
    var embed = window.parent !== window;
    if (embed) document.body.classList.add('embed');

    var state = cfg.state, methods = cfg.methods, types = cfg.types, urls = cfg.urls;
    var view = document.getElementById('pgView');
    var errBox = document.getElementById('pgErr');
    var busy = false, timer = null, notified = false;

    window.pgPost = function (event) {
        if (embed) window.parent.postMessage({ source: 'teleios-pay', event: event, status: state.status }, '*');
    };

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function showErr(msg) { errBox.textContent = msg; errBox.style.display = msg ? 'block' : 'none'; }
    function deadline() { return new Date(state.expires_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }); }

    function render() {
        if (state.status === 'paid') {
            view.innerHTML = '<div class="center"><div class="ok" style="font-size:44px">&#10003;</div><h3 style="margin:4px 0">Pembayaran berhasil</h3><p class="muted">Terima kasih. Status pesanan Anda akan diperbarui oleh ' + esc(cfg.merchant) + '.</p></div>'
                + (state.return_url && !embed ? '<div class="center"><a class="btn" href="' + esc(state.return_url) + '">Kembali ke website</a></div>' : '');
            if (!notified) { notified = true; pgPost('success'); }
            stopPoll();
            return;
        }
        if (state.status !== 'pending') {
            var text = { expired: 'Batas waktu pembayaran sudah habis.', failed: 'Pembayaran gagal.', cancelled: 'Pembayaran dibatalkan.' }[state.status] || 'Pembayaran tidak bisa dilanjutkan.';
            view.innerHTML = '<div class="center"><div class="bad" style="font-size:40px">!</div><p>' + text + '</p><p class="muted">Silakan buat pesanan baru dari website.</p></div>';
            stopPoll();
            return;
        }
        if (!state.method) {
            var html = '<div class="label" style="margin-bottom:6px">Pilih metode pembayaran</div>', lastType = null;
            if (!methods.length) html += '<p class="muted">Metode pembayaran belum tersedia.</p>';
            methods.forEach(function (m) {
                if (m.type !== lastType) { html += '<div class="group">' + esc(types[m.type] || m.type) + '</div>'; lastType = m.type; }
                html += '<button class="method" type="button" data-code="' + esc(m.code) + '"><span>' + esc(m.name) + '</span><small>&rsaquo;</small></button>';
            });
            html += '<p class="muted" style="margin-top:12px">Bayar sebelum ' + esc(deadline()) + '</p>';
            view.innerHTML = '<div class="methods">' + html + '</div>';
            view.querySelectorAll('.method').forEach(function (b) { b.addEventListener('click', function () { choose(b.dataset.code); }); });
            return;
        }
        var detail = '<div class="label">' + esc(state.method_name) + '</div>';
        if (state.va_number) {
            detail += '<div class="box"><div class="muted">Nomor Virtual Account</div><div class="va" id="pgVa">' + esc(state.va_number) + '</div><button class="btn light" type="button" id="pgCopy" style="margin-top:10px">Salin nomor</button></div>';
        } else if (state.qr_svg) {
            detail += '<div class="box">' + state.qr_svg + '<div class="muted" style="margin-top:6px">Scan dengan aplikasi bank / e-wallet apa pun</div></div>';
        } else if (state.payment_url) {
            detail += '<div class="box"><p class="muted">Lanjutkan pembayaran di halaman penyedia.</p><a class="btn" href="' + esc(state.payment_url) + '" target="_blank" rel="noopener">Bayar sekarang</a></div>';
        } else {
            detail += '<div class="box muted">Menyiapkan pembayaran...</div>';
        }
        detail += '<p class="muted center" style="margin-top:12px">Bayar sebelum ' + esc(deadline()) + '. Halaman ini otomatis berubah setelah pembayaran diterima.</p>';
        view.innerHTML = detail;
        var copy = document.getElementById('pgCopy');
        if (copy) copy.addEventListener('click', function () {
            navigator.clipboard && navigator.clipboard.writeText(state.va_number).then(function () { copy.textContent = 'Tersalin'; });
        });
        startPoll();
    }

    function choose(code) {
        if (busy) return;
        busy = true; showErr('');
        view.querySelectorAll('.method').forEach(function (b) { b.disabled = true; });
        fetch(urls.method, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ method: code }) })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                busy = false;
                if (!res.ok) { showErr(res.j.message || 'Gagal memproses metode ini.'); render(); return; }
                state = res.j; render();
            })
            .catch(function () { busy = false; showErr('Koneksi terputus, silakan coba lagi.'); render(); });
    }

    function startPoll() { if (!timer) timer = setInterval(poll, 4000); }
    function stopPoll() { if (timer) { clearInterval(timer); timer = null; } }
    function poll() {
        fetch(urls.status, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && (j.status !== state.status || j.va_number !== state.va_number || j.qr_svg !== state.qr_svg)) { state = j; render(); } })
            .catch(function () {});
    }

    render();
    if (state.status === 'pending' && state.method) startPoll();
})();
</script>
</body>
</html>
