@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        {{-- Summary cards — counts derived client-side from the currently
             loaded page's filters (see updateSummary() in the script
             below), same "4 KPI cards above a filterable table" layout as
             chat.message-schedules.index. --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-primary-subtle text-primary flex-shrink-0">
                            <i class="ri-chat-3-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Percakapan</div>
                            <h4 class="mb-0" id="wa-conv-stat-total">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-warning-subtle text-warning flex-shrink-0">
                            <i class="ri-time-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Menunggu Agent</div>
                            <h4 class="mb-0" id="wa-conv-stat-open">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-info-subtle text-info flex-shrink-0">
                            <i class="ri-hourglass-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Menunggu Pelanggan</div>
                            <h4 class="mb-0" id="wa-conv-stat-pending">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-danger-subtle text-danger flex-shrink-0">
                            <i class="ri-alarm-warning-line fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">SLA Terlambat</div>
                            <h4 class="mb-0" id="wa-conv-stat-breached">-</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Percakapan</h4>
                        <p class="text-muted mb-0">Antrian chat ops di semua device — status, SLA, dan siapa yang menangani.</p>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select id="wa-conv-filter-status" class="form-select" style="max-width: 200px;">
                        <option value="">Semua Status</option>
                        <option value="open">Baru / Menunggu Agent</option>
                        <option value="pending">Menunggu Pelanggan</option>
                        <option value="resolved">Selesai</option>
                    </select>
                    <select id="wa-conv-filter-assigned" class="form-select" style="max-width: 200px;">
                        <option value="">Semua Assignee</option>
                        <option value="me">Ditugaskan ke Saya</option>
                    </select>
                    <div class="form-check d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" class="form-check-input" id="wa-conv-filter-breached" style="margin-top:0;">
                        <label class="form-check-label" for="wa-conv-filter-breached">Hanya yang SLA-nya terlambat</label>
                    </div>
                </div>

                {{-- min-width per column, not just the table, keeps every
                     badge/button on one line — table-responsive scrolls
                     horizontally past this on small screens rather than
                     squeezing columns into a stacked/cramped mess. Same
                     approach chat.message-schedules.index already uses. --}}
                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 1200px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 220px;">Kontak</th>
                                <th style="min-width: 130px;">Device</th>
                                <th style="min-width: 120px;">Status</th>
                                <th style="min-width: 170px;">SLA</th>
                                <th style="min-width: 180px;">Ditugaskan</th>
                                <th style="min-width: 130px;">Pesan Masuk Terakhir</th>
                                <th class="text-end" style="min-width: 110px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="wa-conv-table-body">
                            <tr id="wa-conv-table-empty">
                                <td colspan="7" class="text-center text-muted py-4">Memuat percakapan...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="mt-3 d-flex justify-content-end">
                    <ul class="pagination mb-0" id="wa-conv-pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dataUrl = {{ \Illuminate\Support\Js::from(route('chat.conversations.queue')) }};
        const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};
        const teamMembers = {{ \Illuminate\Support\Js::from($teamMembers) }};
        const inboxUrlTemplate = {{ \Illuminate\Support\Js::from(route('inbox.index', ['device' => '__DEVICE__'])) }};
        const assignUrlTemplate = {{ \Illuminate\Support\Js::from(route('inbox.conversation.assign', ['device' => '__DEVICE__', 'jid' => '__JID__'])) }};
        const statusUrlTemplate = {{ \Illuminate\Support\Js::from(route('inbox.conversation.status', ['device' => '__DEVICE__', 'jid' => '__JID__'])) }};
        const devicesListUrl = {{ \Illuminate\Support\Js::from(route('chat.connect-device.list')) }};

        const tableBody = document.getElementById('wa-conv-table-body');
        const pagination = document.getElementById('wa-conv-pagination');
        const statusFilter = document.getElementById('wa-conv-filter-status');
        const assignedFilter = document.getElementById('wa-conv-filter-assigned');
        const breachedFilter = document.getElementById('wa-conv-filter-breached');

        let currentPage = 1;
        let devicePhoneById = {};

        function fetchJson(url, options) {
            options = options || {};
            // See the same fix/comment in chatbot-flows/index.blade.php —
            // Object.assign({headers: defaults}, options) replaces the
            // whole headers object instead of merging it, silently
            // dropping these defaults whenever a caller passes its own
            // headers (X-CSRF-TOKEN, etc.).
            const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
            return fetch(url, Object.assign({}, options, { headers: headers }))
                .then(function (res) { return res.json(); });
        }

        function urlFor(template, device, jid) {
            return template.replace('__DEVICE__', device).replace('__JID__', encodeURIComponent(jid || ''));
        }

        function timeLabel(iso) {
            if (!iso) return '-';
            const date = new Date(iso);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        }

        function relativeLabel(iso) {
            if (!iso) return null;
            const date = new Date(iso);
            if (isNaN(date.getTime())) return null;
            const diffMs = date.getTime() - Date.now();
            const diffMin = Math.round(diffMs / 60000);
            const abs = Math.abs(diffMin);
            const unit = abs < 60 ? (abs + ' menit') : (abs < 1440 ? (Math.round(abs / 60) + ' jam') : (Math.round(abs / 1440) + ' hari'));
            return diffMin >= 0 ? ('dalam ' + unit) : (unit + ' lalu');
        }

        function statusMeta(status) {
            switch (status) {
                case 'open':
                    return { label: 'Baru / Menunggu Agent', badge: 'bg-warning-subtle text-warning', icon: 'ri-time-line' };
                case 'pending':
                    return { label: 'Menunggu Pelanggan', badge: 'bg-info-subtle text-info', icon: 'ri-hourglass-line' };
                case 'resolved':
                    return { label: 'Selesai', badge: 'bg-success-subtle text-success', icon: 'ri-check-double-line' };
                default:
                    return { label: status, badge: 'bg-secondary-subtle text-secondary', icon: 'ri-question-line' };
            }
        }

        function loadDevices() {
            return fetchJson(devicesListUrl).then(function (data) {
                devicePhoneById = {};
                (data.devices || []).forEach(function (d) { devicePhoneById[d.id] = d.phone_number; });
            });
        }

        function renderRow(conv) {
            const tr = document.createElement('tr');

            // Kontak
            const contactTd = document.createElement('td');
            const contactName = document.createElement('div');
            contactName.className = 'fw-semibold';
            contactName.textContent = conv.contact_name || conv.contact_phone || 'Kontak Baru';
            contactTd.appendChild(contactName);
            if (conv.contact_phone && conv.contact_name) {
                const phoneSmall = document.createElement('div');
                phoneSmall.className = 'text-muted small';
                phoneSmall.textContent = conv.contact_phone;
                contactTd.appendChild(phoneSmall);
            }
            tr.appendChild(contactTd);

            // Device
            const deviceTd = document.createElement('td');
            const phone = devicePhoneById[conv.device_id];
            deviceTd.innerHTML = '<span class="badge bg-dark-subtle text-dark"><i class="ri-smartphone-line"></i> ' + (phone ? ('+' + phone) : 'Device') + '</span>';
            tr.appendChild(deviceTd);

            // Status
            const statusTd = document.createElement('td');
            const meta = statusMeta(conv.status);
            statusTd.innerHTML = '<span class="badge ' + meta.badge + '"><i class="' + meta.icon + '"></i> ' + meta.label + '</span>';
            tr.appendChild(statusTd);

            // SLA
            const slaTd = document.createElement('td');
            const slaWrap = document.createElement('div');
            slaWrap.className = 'd-flex flex-wrap gap-1';
            if (conv.status !== 'resolved') {
                if (conv.first_response_breached) {
                    slaWrap.innerHTML += '<span class="badge bg-danger-subtle text-danger"><i class="ri-alarm-warning-line"></i> Respon Terlambat</span>';
                } else if (!conv.first_response_at && conv.sla_first_response_due_at) {
                    const rel = relativeLabel(conv.sla_first_response_due_at);
                    slaWrap.innerHTML += '<span class="badge bg-secondary-subtle text-secondary" title="Batas respon pertama">Respon ' + (rel || '-') + '</span>';
                }
                if (conv.resolution_breached) {
                    slaWrap.innerHTML += '<span class="badge bg-danger-subtle text-danger"><i class="ri-alarm-warning-line"></i> Penyelesaian Terlambat</span>';
                }
            } else {
                slaWrap.innerHTML = '<span class="text-muted small">Selesai ' + (relativeLabel(conv.resolved_at) || '') + '</span>';
            }
            slaTd.appendChild(slaWrap);
            tr.appendChild(slaTd);

            // Assignee
            const assignTd = document.createElement('td');
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.style.maxWidth = '180px';
            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = 'Belum Ditugaskan';
            select.appendChild(emptyOpt);
            teamMembers.forEach(function (member) {
                const opt = document.createElement('option');
                opt.value = member.id;
                opt.textContent = member.name;
                if (member.id === conv.assigned_to) opt.selected = true;
                select.appendChild(opt);
            });
            select.addEventListener('change', function () {
                select.disabled = true;
                fetchJson(urlFor(assignUrlTemplate, conv.device_id, conv.chat_jid), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ assigned_to: select.value || null }),
                }).finally(function () { select.disabled = false; });
            });
            assignTd.appendChild(select);
            tr.appendChild(assignTd);

            // Last inbound
            const lastTd = document.createElement('td');
            lastTd.className = 'text-muted small';
            lastTd.textContent = timeLabel(conv.last_inbound_at);
            tr.appendChild(lastTd);

            // Actions
            const actionTd = document.createElement('td');
            actionTd.className = 'text-end';
            const actionGroup = document.createElement('div');
            actionGroup.className = 'd-flex flex-nowrap justify-content-end gap-1';

            const openBtn = document.createElement('a');
            openBtn.className = 'btn btn-sm btn-outline-primary';
            openBtn.innerHTML = '<i class="ri-message-3-line"></i>';
            openBtn.title = 'Buka di Inbox';
            openBtn.href = urlFor(inboxUrlTemplate, conv.device_id);
            actionGroup.appendChild(openBtn);

            if (conv.status !== 'resolved') {
                const resolveBtn = document.createElement('button');
                resolveBtn.type = 'button';
                resolveBtn.className = 'btn btn-sm btn-outline-success';
                resolveBtn.innerHTML = '<i class="ri-check-line"></i>';
                resolveBtn.title = 'Tandai Selesai';
                resolveBtn.addEventListener('click', function () {
                    resolveBtn.disabled = true;
                    fetchJson(urlFor(statusUrlTemplate, conv.device_id, conv.chat_jid), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status: 'resolved' }),
                    }).finally(function () { loadConversations(); });
                });
                actionGroup.appendChild(resolveBtn);
            } else {
                const reopenBtn = document.createElement('button');
                reopenBtn.type = 'button';
                reopenBtn.className = 'btn btn-sm btn-outline-secondary';
                reopenBtn.innerHTML = '<i class="ri-refresh-line"></i>';
                reopenBtn.title = 'Buka Kembali';
                reopenBtn.addEventListener('click', function () {
                    reopenBtn.disabled = true;
                    fetchJson(urlFor(statusUrlTemplate, conv.device_id, conv.chat_jid), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status: 'open' }),
                    }).finally(function () { loadConversations(); });
                });
                actionGroup.appendChild(reopenBtn);
            }

            actionTd.appendChild(actionGroup);
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
                    loadConversations();
                });
                li.appendChild(a);
                pagination.appendChild(li);
            }
        }

        function updateSummary(conversations) {
            document.getElementById('wa-conv-stat-total').textContent = conversations.length;
            document.getElementById('wa-conv-stat-open').textContent = conversations.filter(function (c) { return c.status === 'open'; }).length;
            document.getElementById('wa-conv-stat-pending').textContent = conversations.filter(function (c) { return c.status === 'pending'; }).length;
            document.getElementById('wa-conv-stat-breached').textContent = conversations.filter(function (c) { return c.first_response_breached || c.resolution_breached; }).length;
        }

        function loadConversations() {
            const params = new URLSearchParams();
            params.set('per_page', '25');
            params.set('page', String(currentPage));
            if (statusFilter.value) params.set('status', statusFilter.value);
            if (breachedFilter.checked) params.set('breached_only', '1');

            if (assignedFilter.value === 'me') {
                params.set('assigned_to', 'me');
            }

            fetchJson(dataUrl + '?' + params.toString()).then(function (data) {
                const conversations = data.conversations || [];
                tableBody.innerHTML = '';

                if (conversations.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="7" class="text-center text-muted py-4">Belum ada percakapan yang cocok dengan filter ini.</td>';
                    tableBody.appendChild(row);
                } else {
                    conversations.forEach(function (conv) { tableBody.appendChild(renderRow(conv)); });
                }

                updateSummary(conversations);
                renderPagination(data.meta);
            });
        }

        [statusFilter, assignedFilter].forEach(function (el) {
            el.addEventListener('change', function () { currentPage = 1; loadConversations(); });
        });
        breachedFilter.addEventListener('change', function () { currentPage = 1; loadConversations(); });

        loadDevices().then(loadConversations);
        setInterval(loadConversations, 15000);
    });
</script>
@endsection
