@extends('layouts.dashboard')

{{--
    Live Chat Inbox -- percakapan dari Live Chat Widget. Data diambil lewat
    polling JSON (ChatWidgetInboxController). Semua isi pesan dirender
    dengan textContent supaya pesan pengunjung tidak bisa menyisipkan HTML.
--}}
@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="row g-0" style="min-height: 70vh;">
            <div class="col-md-4 border-end">
                <div class="p-3 border-bottom">
                    <h5 class="mb-2">Live Chat Inbox</h5>
                    <select id="cwFilter" class="form-select form-select-sm">
                        <option value="">Semua yang aktif</option>
                        @foreach (\App\Models\ChatWidgetConversation::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="cwList" class="list-group list-group-flush" style="max-height: 65vh; overflow-y: auto;">
                    <div class="p-3 text-muted small">Memuat percakapan...</div>
                </div>
            </div>
            <div class="col-md-8 d-flex flex-column">
                <div id="cwEmpty" class="m-auto text-muted text-center p-4">
                    <i class="ri-chat-3-line fs-1"></i>
                    <div>Pilih percakapan di sebelah kiri.</div>
                </div>
                <div id="cwPanel" class="d-none flex-column h-100">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0" id="cwName"></h6>
                            <small class="text-muted" id="cwMeta"></small>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <span class="badge bg-light text-dark" id="cwStatus"></span>
                            <button class="btn btn-sm btn-outline-primary" id="cwTake" type="button">Ambil alih</button>
                            <button class="btn btn-sm btn-outline-secondary" id="cwClose" type="button">Selesai</button>
                        </div>
                    </div>
                    <div id="cwMessages" class="flex-grow-1 p-3 bg-light" style="overflow-y: auto; max-height: 55vh;"></div>
                    <form id="cwReply" class="p-2 border-top d-flex gap-2">
                        <textarea id="cwText" class="form-control" rows="1" maxlength="2000" placeholder="Balas sebagai CS (AI otomatis berhenti di chat ini)..." required></textarea>
                        <button class="btn btn-primary" type="submit">Kirim</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var urls = {
        list: @json(route('chat.widget-inbox.conversations')),
        base: @json(route('chat.widget-inbox.index')),
    };
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var current = null, lastId = 0, listTimer = null, chatTimer = null;
    var el = function (id) { return document.getElementById(id); };
    var badge = { ai: 'bg-info-subtle text-info', waiting: 'bg-warning-subtle text-warning', agent: 'bg-success-subtle text-success', closed: 'bg-secondary-subtle text-secondary' };

    function req(method, url, body) {
        return fetch(url, {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (r) { return r.json(); });
    }

    function text(tag, value, cls) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        node.textContent = value || '';
        return node;
    }

    function loadList() {
        req('GET', urls.list + '?status=' + encodeURIComponent(el('cwFilter').value)).then(function (data) {
            var list = el('cwList');
            list.innerHTML = '';
            if (!data.conversations.length) { list.appendChild(text('div', 'Belum ada percakapan.', 'p-3 text-muted small')); return; }
            data.conversations.forEach(function (c) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action' + (current && current.id === c.id ? ' active' : '');
                var top = document.createElement('div');
                top.className = 'd-flex justify-content-between';
                top.appendChild(text('strong', c.name));
                top.appendChild(text('span', c.status_label, 'badge ' + (badge[c.status] || '')));
                item.appendChild(top);
                item.appendChild(text('div', c.last, 'small text-truncate'));
                item.appendChild(text('div', (c.widget || '') + ' · ' + (c.at || ''), 'small opacity-75'));
                item.addEventListener('click', function () { open(c); });
                list.appendChild(item);
            });
        });
    }

    function renderMessages(messages) {
        var box = el('cwMessages');
        messages.forEach(function (m) {
            if (m.id <= lastId) return;
            lastId = m.id;
            var mine = m.sender === 'agent' || m.sender === 'ai';
            var wrap = document.createElement('div');
            wrap.className = 'd-flex mb-2 ' + (m.sender === 'system' ? 'justify-content-center' : mine ? 'justify-content-end' : 'justify-content-start');
            var bubble = text('div', m.body, m.sender === 'system' ? 'small text-muted fst-italic' : 'p-2 rounded-3 ' + (mine ? 'bg-primary text-white' : 'bg-white border'));
            bubble.style.maxWidth = '75%';
            bubble.style.whiteSpace = 'pre-wrap';
            if (m.sender !== 'system') {
                bubble.appendChild(text('div', (m.sender === 'ai' ? 'AI' : m.sender === 'agent' ? (m.name || 'CS') : 'Pengunjung') + ' · ' + (m.at || ''), 'small opacity-75 mt-1'));
            }
            wrap.appendChild(bubble);
            box.appendChild(wrap);
        });
        box.scrollTop = box.scrollHeight;
    }

    function applyState(state) {
        renderMessages(state.messages);
        el('cwStatus').textContent = state.status_label;
        el('cwTake').classList.toggle('d-none', state.status === 'agent');
        el('cwClose').classList.toggle('d-none', state.status === 'closed');
    }

    function open(c) {
        current = c; lastId = 0;
        el('cwEmpty').classList.add('d-none');
        el('cwPanel').classList.remove('d-none');
        el('cwPanel').classList.add('d-flex');
        el('cwMessages').innerHTML = '';
        el('cwName').textContent = c.name;
        el('cwMeta').textContent = [c.phone, c.widget, c.page_url].filter(Boolean).join(' · ');
        poll();
        clearInterval(chatTimer);
        chatTimer = setInterval(poll, 3000);
        loadList();
    }

    function poll() {
        if (!current) return;
        req('GET', urls.base + '/' + current.id + '/messages?after=' + lastId).then(applyState);
    }

    function action(path, body) {
        return req('POST', urls.base + '/' + current.id + '/' + path, Object.assign({ after: lastId }, body || {})).then(function (state) { applyState(state); loadList(); });
    }

    el('cwTake').addEventListener('click', function () { action('take-over'); });
    el('cwClose').addEventListener('click', function () { action('close'); });
    el('cwFilter').addEventListener('change', loadList);

    el('cwReply').addEventListener('submit', function (e) {
        e.preventDefault();
        var value = el('cwText').value.trim();
        if (!value || !current) return;
        el('cwText').value = '';
        action('reply', { body: value });
    });

    el('cwText').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); el('cwReply').requestSubmit(); }
    });

    loadList();
    listTimer = setInterval(loadList, 10000);
})();
</script>
@endsection
