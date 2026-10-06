{{--
    Jendela chat Live Chat Widget -- dibuka di dalam iframe oleh
    public/widget.js. Halaman mandiri (tanpa layout dashboard) supaya
    ringan. Semua isi pesan ditampilkan lewat textContent (bukan
    innerHTML) jadi pesan tidak bisa menyisipkan HTML/script.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $widget->setting('title') }}</title>
    <style>
        :root { --brand: {{ $widget->setting('color') }}; }
        * { box-sizing: border-box; }
        /* Atribut hidden harus menang atas display:grid/flex di bawah --
           tanpa ini form nama/WA tetap tampil bersamaan dengan chat. */
        [hidden] { display: none !important; }
        html, body { margin: 0; height: 100%; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 14px; color: #1f2937; background: #fff; }
        /* Kolom flex: header & kolom pesan tetap, hanya area chat yang
           scroll. min-height: 0 wajib -- tanpa itu chat panjang mendorong
           kolom pesan & tombol keluar dari layar. */
        .cw { display: flex; flex-direction: column; height: 100%; height: 100dvh; overflow: hidden; }
        .cw > * { flex-shrink: 0; }
        .cw-head { background: var(--brand); color: #fff; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; }
        .cw-head b { font-size: 15px; }
        .cw-head small { display: block; opacity: .85; font-size: 12px; }
        .cw-close { background: none; border: 0; color: #fff; font-size: 22px; cursor: pointer; line-height: 1; }
        .cw-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 12px; background: #f5f6f8; }
        .cw-msg { max-width: 82%; margin: 6px 0; padding: 8px 11px; border-radius: 14px; white-space: pre-wrap; word-wrap: break-word; line-height: 1.4; }
        .cw-msg small { display: block; font-size: 11px; opacity: .6; margin-top: 3px; }
        .cw-visitor { margin-left: auto; background: var(--brand); color: #fff; border-bottom-right-radius: 4px; }
        .cw-ai, .cw-agent { background: #fff; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
        .cw-system { margin: 8px auto; background: transparent; color: #6b7280; font-size: 12px; text-align: center; }
        .cw-typing { color: #6b7280; font-size: 12px; padding: 0 14px 6px; min-height: 18px; background: #f5f6f8; }
        .cw-start { flex: 1 1 auto; min-height: 0; padding: 16px; display: grid; gap: 8px; align-content: start; overflow-y: auto; }
        .cw-input, .cw-start input { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 11px; font: inherit; }
        .cw-foot { border-top: 1px solid #e5e7eb; padding: 8px; display: flex; gap: 6px; align-items: flex-end; }
        .cw-foot textarea { flex: 1; min-width: 0; resize: none; max-height: 96px; overflow-y: auto; line-height: 1.4; }
        .cw-foot .cw-btn { flex-shrink: 0; }
        /* Font 16px di HP supaya iOS tidak zoom saat kolom diketik. */
        @media (max-width: 480px) { .cw-input, .cw-start input { font-size: 16px; } }
        .cw-btn { background: var(--brand); color: #fff; border: 0; border-radius: 10px; padding: 9px 14px; font: inherit; cursor: pointer; }
        .cw-btn:disabled { opacity: .5; cursor: default; }
        .cw-link { background: none; border: 0; color: var(--brand); font: inherit; font-size: 12px; cursor: pointer; padding: 4px 12px 8px; text-align: left; }
        .cw-note { font-size: 11px; color: #9ca3af; text-align: center; padding: 0 0 6px; }
        .cw-error { color: #b91c1c; font-size: 12px; }
    </style>
</head>
<body>
<div class="cw">
    <div class="cw-head">
        <div><b>{{ $widget->setting('title') }}</b><small id="cwSub">Kami siap membantu</small></div>
        <button class="cw-close" type="button" aria-label="Tutup" onclick="parent.postMessage({cw: 'close'}, '*')">&times;</button>
    </div>

    <form class="cw-start" id="cwStart" hidden>
        <div>{{ $widget->setting('greeting') }}</div>
        <input name="name" maxlength="100" placeholder="Nama Anda" required>
        <input name="phone" maxlength="30" placeholder="No. WhatsApp" inputmode="tel" required>
        <div class="cw-error" id="cwStartError"></div>
        <button class="cw-btn" type="submit">Mulai chat</button>
    </form>

    <div class="cw-body" id="cwBody" hidden></div>
    <div class="cw-typing" id="cwTyping" hidden></div>
    <button class="cw-link" id="cwHuman" type="button" hidden>Bicara dengan tim kami</button>
    <div class="cw-start" id="cwEnded" hidden style="flex: 0;">
        <button class="cw-btn" id="cwRestart" type="button">Mulai chat baru</button>
    </div>
    <form class="cw-foot" id="cwForm" hidden>
        <textarea class="cw-input" id="cwText" rows="1" maxlength="1000" placeholder="Tulis pesan..." required></textarea>
        <button class="cw-btn" type="submit" id="cwSend">Kirim</button>
    </form>
    <div class="cw-note" id="cwNote" hidden>Dibantu AI &middot; jawaban bisa saja keliru</div>
</div>

<script>
(function () {
    var api = @json(url('/api/chat-widget/'.$widget->public_key));
    var requireContact = @json((bool) $widget->setting('require_contact'));
    var aiEnabled = @json((bool) $widget->ai_enabled);
    var storeKey = 'cw_' + @json($widget->public_key);
    var pageUrl = new URLSearchParams(location.search).get('page') || '';
    var token = null, lastId = 0, status = null, timer = null;
    var el = function (id) { return document.getElementById(id); };

    try { token = localStorage.getItem(storeKey); } catch (e) {}

    function call(method, path, body) {
        var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
        if (token) headers['X-Visitor-Token'] = token;
        return fetch(api + path, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; }); });
    }

    function render(messages) {
        messages.forEach(function (m) {
            if (m.id <= lastId) return;
            lastId = m.id;
            var div = document.createElement('div');
            div.className = 'cw-msg cw-' + m.sender;
            div.textContent = m.body;
            if (m.sender !== 'system') {
                var meta = document.createElement('small');
                meta.textContent = (m.sender === 'agent' ? (m.name || 'CS') + ' · ' : m.sender === 'ai' ? 'Asisten · ' : '') + (m.at || '');
                div.appendChild(meta);
            }
            el('cwBody').appendChild(div);
        });
        el('cwBody').scrollTop = el('cwBody').scrollHeight;
    }

    function setStatus(next) {
        status = next;
        var waiting = status === 'ai' && el('cwBody').lastElementChild && el('cwBody').lastElementChild.classList.contains('cw-visitor');
        el('cwTyping').hidden = !waiting;
        el('cwTyping').textContent = waiting ? 'Sedang mengetik...' : '';
        el('cwHuman').hidden = status !== 'ai';
        el('cwNote').hidden = !(aiEnabled && status === 'ai');
        el('cwSub').textContent = status === 'agent' ? 'Terhubung dengan tim kami' : status === 'waiting' ? 'Menunggu tim kami' : 'Kami siap membantu';

        // Percakapan selesai (ditutup CS / tidak ada balasan): kolom pesan
        // diganti tombol "Mulai chat baru" -> sesi baru dari awal.
        var ended = status === 'closed';
        el('cwForm').hidden = ended;
        el('cwEnded').hidden = !ended;
        if (ended) clearInterval(timer);
    }

    function poll() {
        call('GET', '/messages?after=' + lastId).then(function (r) {
            if (r.status === 401) { forget(); return; }
            if (r.ok) { render(r.data.messages); setStatus(r.data.status); }
        }).catch(function () {});
    }

    function forget() {
        token = null; lastId = 0; status = null; clearInterval(timer);
        el('cwBody').innerHTML = '';
        ['cwBody', 'cwForm', 'cwEnded', 'cwHuman', 'cwNote', 'cwTyping'].forEach(function (id) { el(id).hidden = true; });
        try { localStorage.removeItem(storeKey); } catch (e) {}
        boot();
    }

    function openChat(data) {
        if (data.token) { token = data.token; try { localStorage.setItem(storeKey, token); } catch (e) {} }
        el('cwStart').hidden = true;
        el('cwBody').hidden = false;
        render(data.messages);
        setStatus(data.status);
        clearInterval(timer);
        timer = setInterval(poll, 3000);
    }

    function start(contact) {
        return call('POST', '/session', Object.assign({ page_url: pageUrl }, contact || {})).then(function (r) {
            if (r.ok) { openChat(r.data); return null; }
            return (r.data && r.data.message) || 'Chat belum bisa dimulai. Coba lagi sebentar.';
        });
    }

    function boot() {
        if (requireContact && !token) { el('cwStart').hidden = false; return; }
        start();
    }

    el('cwStart').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        start({ name: f.name.value, phone: f.phone.value }).then(function (error) { el('cwStartError').textContent = error || ''; });
    });

    el('cwForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var text = el('cwText').value.trim();
        if (!text) return;
        el('cwSend').disabled = true;
        call('POST', '/messages', { body: text }).then(function (r) {
            el('cwSend').disabled = false;
            if (r.status === 429) { el('cwTyping').hidden = false; el('cwTyping').textContent = 'Terlalu banyak pesan, tunggu sebentar ya.'; return; }
            if (!r.ok) return;
            el('cwText').value = '';
            autosize();
            render([r.data.message]);
            setStatus(status === 'closed' ? 'ai' : status);
        });
    });

    // Kolom pesan ikut tinggi teks (maks. sekitar 4 baris, sisanya scroll).
    function autosize() {
        var t = el('cwText');
        t.style.height = 'auto';
        t.style.height = Math.min(t.scrollHeight, 96) + 'px';
    }
    el('cwText').addEventListener('input', autosize);

    el('cwText').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); el('cwForm').requestSubmit(); }
    });

    el('cwRestart').addEventListener('click', forget);

    el('cwHuman').addEventListener('click', function () {
        call('POST', '/handover').then(function (r) { if (r.ok) poll(); });
    });

    boot();
})();
</script>
</body>
</html>
