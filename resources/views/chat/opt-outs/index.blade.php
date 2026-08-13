@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Daftar Opt-out (Berhenti Berlangganan)</h4>
                        <p class="text-muted mb-0">Nomor yang tidak lagi menerima pesan broadcast — otomatis bertambah saat pelanggan membalas STOP, atau tambahkan manual di sini.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wa-optout-add-modal">
                        <i class="ri-add-line"></i> Tambah Manual
                    </button>
                </div>

                <form class="d-flex flex-wrap gap-2 mb-3" onsubmit="return false;">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" id="wa-optout-search" class="form-control" placeholder="Cari nomor...">
                        <button type="button" id="wa-optout-search-btn" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 900px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 160px;">Nomor</th>
                                <th style="min-width: 130px;">Sumber</th>
                                <th style="min-width: 220px;">Catatan</th>
                                <th style="min-width: 150px;">Ditambahkan Oleh</th>
                                <th style="min-width: 170px;">Tanggal</th>
                                <th class="text-end" style="min-width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="wa-optout-table-body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="mt-3 d-flex justify-content-end">
                    <ul class="pagination mb-0" id="wa-optout-pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="wa-optout-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Nomor Opt-out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" id="wa-optout-phone" class="form-control" placeholder="Contoh: 6281234567890">
                    <div class="invalid-feedback d-block text-danger fs-12" id="wa-optout-phone-error" style="display:none !important;"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea id="wa-optout-note" class="form-control" rows="2" placeholder="Alasan / sumber permintaan berhenti..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="wa-optout-save-btn">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const listUrl = {{ \Illuminate\Support\Js::from(route('chat.opt-outs.list')) }};
        const storeUrl = {{ \Illuminate\Support\Js::from(route('chat.opt-outs.store')) }};
        const destroyUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.opt-outs.destroy', ['optOut' => '__ID__'])) }};
        const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};

        const tableBody = document.getElementById('wa-optout-table-body');
        const pagination = document.getElementById('wa-optout-pagination');
        const searchInput = document.getElementById('wa-optout-search');
        const searchBtn = document.getElementById('wa-optout-search-btn');

        const addModalEl = document.getElementById('wa-optout-add-modal');
        const addModal = new bootstrap.Modal(addModalEl);
        const phoneInput = document.getElementById('wa-optout-phone');
        const phoneError = document.getElementById('wa-optout-phone-error');
        const noteInput = document.getElementById('wa-optout-note');
        const saveBtn = document.getElementById('wa-optout-save-btn');

        let currentPage = 1;

        function fetchJson(url, options) {
            options = options || {};
            // See the same fix/comment in chatbot-flows/index.blade.php —
            // Object.assign({headers: defaults}, options) replaces the
            // whole headers object instead of merging it, silently
            // dropping these defaults whenever a caller passes its own
            // headers (X-CSRF-TOKEN, etc.).
            const headers = Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
            return fetch(url, Object.assign({}, options, { headers: headers }))
                .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); });
        }

        function timeLabel(iso) {
            if (!iso) return '-';
            const date = new Date(iso);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        }

        function sourceBadge(source) {
            return source === 'wa_reply'
                ? '<span class="badge bg-info-subtle text-info"><i class="ri-chat-check-line"></i> Balasan STOP</span>'
                : '<span class="badge bg-secondary-subtle text-secondary"><i class="ri-user-add-line"></i> Manual</span>';
        }

        function renderRow(row) {
            const tr = document.createElement('tr');

            const phoneTd = document.createElement('td');
            phoneTd.innerHTML = '<code>' + row.phone + '</code>';
            tr.appendChild(phoneTd);

            const sourceTd = document.createElement('td');
            sourceTd.innerHTML = sourceBadge(row.source);
            tr.appendChild(sourceTd);

            const noteTd = document.createElement('td');
            noteTd.className = 'text-truncate';
            noteTd.style.maxWidth = '260px';
            noteTd.textContent = row.note || '-';
            tr.appendChild(noteTd);

            const byTd = document.createElement('td');
            byTd.textContent = row.created_by_name || '-';
            tr.appendChild(byTd);

            const dateTd = document.createElement('td');
            dateTd.className = 'text-muted small';
            dateTd.textContent = timeLabel(row.opted_out_at);
            tr.appendChild(dateTd);

            const actionTd = document.createElement('td');
            actionTd.className = 'text-end';
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-sm btn-light text-danger';
            delBtn.title = 'Hapus dari daftar opt-out';
            delBtn.innerHTML = '<i class="ri-delete-bin-line"></i>';
            delBtn.addEventListener('click', function () {
                if (!confirm('Hapus nomor ' + row.phone + ' dari daftar opt-out? Nomor ini akan bisa menerima broadcast lagi.')) return;
                delBtn.disabled = true;
                fetchJson(destroyUrlTemplate.replace('__ID__', row.id), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).finally(function () { loadOptOuts(); });
            });
            actionTd.appendChild(delBtn);
            tr.appendChild(actionTd);

            return tr;
        }

        function renderPagination(meta) {
            pagination.innerHTML = '';
            if (!meta || meta.last_page <= 1) return;

            for (let page = 1; page <= meta.last_page; page++) {
                const li = document.createElement('li');
                li.className = 'page-item' + (page === meta.current_page ? ' active' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = page;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    currentPage = page;
                    loadOptOuts();
                });
                li.appendChild(a);
                pagination.appendChild(li);
            }
        }

        function loadOptOuts() {
            const params = new URLSearchParams();
            params.set('per_page', '25');
            params.set('page', String(currentPage));
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());

            fetchJson(listUrl + '?' + params.toString()).then(function (result) {
                const optOuts = result.body.opt_outs || [];
                tableBody.innerHTML = '';

                if (optOuts.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="6" class="text-center text-muted py-4">Belum ada nomor opt-out.</td>';
                    tableBody.appendChild(row);
                } else {
                    optOuts.forEach(function (row) { tableBody.appendChild(renderRow(row)); });
                }

                renderPagination(result.body.meta);
            });
        }

        searchBtn.addEventListener('click', function () { currentPage = 1; loadOptOuts(); });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { currentPage = 1; loadOptOuts(); }
        });

        saveBtn.addEventListener('click', function () {
            phoneError.style.setProperty('display', 'none', 'important');
            saveBtn.disabled = true;

            fetchJson(storeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone: phoneInput.value.trim(), note: noteInput.value.trim() || null }),
            }).then(function (result) {
                if (!result.ok) {
                    const message = (result.body.errors && result.body.errors.phone && result.body.errors.phone[0])
                        || result.body.message || 'Gagal menambahkan nomor.';
                    phoneError.textContent = message;
                    phoneError.style.setProperty('display', 'block', 'important');
                    return;
                }

                phoneInput.value = '';
                noteInput.value = '';
                addModal.hide();
                currentPage = 1;
                loadOptOuts();
            }).finally(function () { saveBtn.disabled = false; });
        });

        loadOptOuts();
    });
</script>
@endsection
