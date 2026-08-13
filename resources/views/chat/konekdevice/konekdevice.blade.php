@extends('layouts.dashboard')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">Device WhatsApp</h4>
                            <p class="text-muted mb-0">Satu akun bisa menghubungkan lebih dari satu nomor WhatsApp.</p>
                        </div>
                        <button type="button" id="wa-add-device-btn" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Device
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="wa-device-table" class="table table-centered table-hover align-middle mb-0" style="min-width: 780px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px; min-width: 60px;">No</th>
                                    <th style="min-width: 150px;">No Handphone</th>
                                    <th style="min-width: 130px;">Status</th>
                                    <th style="min-width: 170px; white-space: nowrap;">Terakhir Terhubung</th>
                                    <th class="text-end" style="min-width: 270px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="wa-device-table-body">
                                <tr id="wa-device-table-empty">
                                    <td colspan="5" class="text-center text-muted py-4">Memuat device...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- QR pairing modal, shared by "Tambah Device" and per-row "Refresh" --}}
    <div class="modal fade" id="wa-qr-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hubungkan WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="text-muted mb-3">Scan QR code ini menggunakan aplikasi WhatsApp di HP Anda.</p>

                    <div id="wa-qr-wrapper" class="d-flex justify-content-center mb-3">
                        <div id="wa-qr" style="display:inline-block; min-height: 256px;"></div>
                    </div>

                    <div id="wa-qr-status-badge" class="badge bg-warning-subtle text-warning fs-13 px-3 py-2">
                        Menunggu scan...
                    </div>
                    <p class="text-muted fs-12 mt-2 mb-0">QR code diperbarui otomatis oleh WhatsApp, tidak perlu refresh halaman.</p>

                    <div id="wa-qr-connected-info" class="mt-4 d-none">
                        <div class="avatar-item avatar-lg mx-auto rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center mb-3">
                            <i class="ri-check-line fs-2"></i>
                        </div>
                        <p class="mb-0 fw-semibold">WhatsApp berhasil terhubung!</p>
                        <p class="text-muted" id="wa-qr-phone-number"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Device history modal — timestamped log of connect/disconnect/
         logout/reconnect events for one device, so a user can see WHY it
         dropped instead of just a bare "Terputus" badge. Shared by every
         row's "Riwayat" button. See WaConnectDeviceService::logHistory
         (Go) and App\Http\Controllers\Chat\ConnectDeviceController::history(). --}}
    <div class="modal fade" id="wa-history-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Riwayat Koneksi <span id="wa-history-phone" class="text-muted fs-14"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <div id="wa-history-loading" class="text-center text-muted py-4">Memuat...</div>
                    <div id="wa-history-empty" class="d-none text-center text-muted py-4">Belum ada riwayat untuk device ini.</div>
                    <ul id="wa-history-list" class="list-unstyled mb-0"></ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Device health score modal — see App\Services\Chat\DeviceHealthService.
         Shares the "Kesehatan" button per row with the score badge and
         signal breakdown, same shape as the Riwayat modal above but for
         the anti-ban health SCORE rather than the raw event log. --}}
    <div class="modal fade" id="wa-health-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kesehatan Nomor <span id="wa-health-phone" class="text-muted fs-14"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="wa-health-loading" class="text-center text-muted py-4">Memuat...</div>
                    <div id="wa-health-content" class="d-none">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-item avatar-lg rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" id="wa-health-score-circle">
                                <span id="wa-health-score-number" class="fs-4 fw-semibold"></span>
                            </div>
                            <div>
                                <span class="badge fs-13 px-3 py-2" id="wa-health-label-badge"></span>
                                <p class="text-muted fs-12 mb-0 mt-1">Skor 0-100, makin tinggi makin aman dari risiko banned.</p>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-0 fs-13">
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Logout / Banned (7 hari)</span>
                                <span id="wa-health-logged-out" class="fw-semibold"></span>
                            </li>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Terputus (7 hari)</span>
                                <span id="wa-health-disconnected" class="fw-semibold"></span>
                            </li>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">Gagal Sambung Ulang</span>
                                <span id="wa-health-reconnect-failed" class="fw-semibold"></span>
                            </li>
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-muted">Gagal Kirim Broadcast</span>
                                <span id="wa-health-failure-rate" class="fw-semibold"></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .wa-status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
            flex-shrink: 0;
        }
        .wa-status-dot.wa-dot-connected {
            background-color: #22c55e;
            box-shadow: 0 0 0 rgba(34, 197, 94, 0.6);
            animation: wa-pulse-green 1.5s infinite;
        }
        .wa-status-dot.wa-dot-disconnected {
            background-color: #ef4444;
            box-shadow: 0 0 0 rgba(239, 68, 68, 0.6);
            animation: wa-pulse-red 1.5s infinite;
        }
        .wa-status-dot.wa-dot-pending {
            background-color: #f59e0b;
            box-shadow: 0 0 0 rgba(245, 158, 11, 0.6);
            animation: wa-pulse-amber 1.5s infinite;
        }
        @keyframes wa-pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        @keyframes wa-pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        @keyframes wa-pulse-amber {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        .wa-history-item { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .wa-history-item:last-child { border-bottom: none; }
        .wa-history-dot { width: 9px; height: 9px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .wa-history-label { font-weight: 600; font-size: 0.86rem; }
        .wa-history-detail { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
        .wa-history-time { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }

        /* Desktop: buttons stay on one row, always level with each
           other — the table itself has a min-width (see the inline
           style on #wa-device-table) and .table-responsive already
           scrolls horizontally, so there's no need to wrap the action
           buttons onto a second line; wrapping was what made them look
           uneven ("naik turun"). Overridden back to wrap below 768px,
           where each row becomes its own stacked card instead of a
           wide scrolling table — see the media query below. */
        .wa-action-buttons {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            padding-top: 2px;
        }

        /* Icon-only action buttons. Every button (Inbox, Refresh, API Key,
           Riwayat, Kesehatan, Chatbot Flow, Disconnect) shares this exact
           box, so the icons all render at the same size regardless of how
           long the old text label used to be — that's what made them look
           mismatched before. Flat "subtle" background instead of an
           outlined pill for a simpler, more modern look; title="" on each
           button (set in JS) provides a native tooltip in place of the
           removed text label. */
        .wa-icon-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            line-height: 1;
            transition: filter .15s ease, transform .1s ease;
        }
        .wa-icon-btn i {
            font-size: 16px;
            line-height: 1;
        }
        .wa-icon-btn:hover:not(:disabled):not(.disabled) {
            filter: brightness(0.94);
        }
        .wa-icon-btn:active:not(:disabled):not(.disabled) {
            transform: scale(0.92);
        }
        .wa-icon-btn:disabled,
        .wa-icon-btn.disabled {
            opacity: .4;
            pointer-events: none;
        }

        /* Below md: table rows re-flow into stacked "cards" (one per
           device) instead of relying on horizontal scroll — each cell
           becomes its own line, labeled via data-label (set in the
           renderDevices() JS below), so every field (No Handphone,
           Status, Terakhir Terhubung) stays fully visible without
           side-scrolling; only the action buttons area keeps its normal
           left-to-right flow (wrapped, see .wa-action-buttons above). */
        @media (max-width: 767.98px) {
            #wa-device-table thead { display: none; }
            #wa-device-table, #wa-device-table tbody, #wa-device-table tr, #wa-device-table td {
                display: block;
                width: 100%;
                /* Cancels the desktop min-width (see the table's inline
                   style) — without this the stacked-card layout below
                   would still be forced 900px wide and scroll instead
                   of actually stacking. */
                min-width: 0;
            }
            /* Back to wrapping here: each device is its own card on
               mobile (not one wide scrolling table), so 7 buttons in a
               single forced row would overflow the card instead of
               reading as one aligned action row like on desktop. Icon
               buttons are fixed 34x34px (see .wa-icon-btn), so wrapped
               rows line up cleanly instead of the ragged, overlapping
               look the old text-label pills had at narrow widths. */
            #wa-device-table .wa-action-buttons {
                flex-wrap: wrap;
                row-gap: 8px;
            }
            #wa-device-table tbody tr {
                margin-bottom: 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 4px 0;
            }
            #wa-device-table tbody tr:last-child { margin-bottom: 0; }
            #wa-device-table td {
                border-top: none;
                padding: 6px 12px;
            }
            #wa-device-table td[data-label] {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                text-align: right;
            }
            #wa-device-table td[data-label]::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.8rem;
                color: #6b7280;
                text-align: left;
            }
            #wa-device-table td.wa-action-cell {
                text-align: left;
            }
            #wa-device-table td.wa-action-cell .wa-action-buttons {
                justify-content: flex-start;
            }
            #wa-device-table-empty td {
                text-align: center !important;
                display: block !important;
            }
            #wa-device-table-empty td::before { content: none; }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Deferred until DOMContentLoaded: the layout loads Bootstrap's JS
        // bundle in a <script> tag at the very bottom of <body>, AFTER this
        // page's content is parsed. Calling `new bootstrap.Modal(...)`
        // synchronously up front would throw "bootstrap is not defined" and
        // silently abort this entire script — including the loadDevices()
        // call and the "Tambah Device" click handler below — which is why
        // the table used to hang forever on "Memuat device..." and the
        // button did nothing. DOMContentLoaded only fires once every
        // blocking script on the page (bootstrap included) has already run.
        document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const listUrl = {{ \Illuminate\Support\Js::from(route('chat.connect-device.list')) }};
            const addUrl = {{ \Illuminate\Support\Js::from(route('chat.connect-device.add')) }};
            const statusUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.status', ['device' => '__ID__'])) }};
            const reconnectUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.reconnect', ['device' => '__ID__'])) }};
            const disconnectUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.disconnect', ['device' => '__ID__'])) }};
            const inboxUrlTemplate = {{ \Illuminate\Support\Js::from(route('inbox.index', ['device' => '__ID__'])) }};
            const apiKeyPageUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.api-key.show', ['device' => '__ID__'])) }};
            const historyUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.history', ['device' => '__ID__'])) }};
            const healthUrlTemplate = {{ \Illuminate\Support\Js::from(route('chat.connect-device.health', ['device' => '__ID__'])) }};
            const chatbotFlowsUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.index', ['device' => '__ID__'])) }};
            const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};

            const tableBody = document.getElementById('wa-device-table-body');
            const addDeviceBtn = document.getElementById('wa-add-device-btn');

            const qrModalEl = document.getElementById('wa-qr-modal');
            const qrModal = new bootstrap.Modal(qrModalEl);
            const qrWrapper = document.getElementById('wa-qr-wrapper');
            const qrContainer = document.getElementById('wa-qr');
            const qrStatusBadge = document.getElementById('wa-qr-status-badge');
            const qrConnectedInfo = document.getElementById('wa-qr-connected-info');
            const qrPhoneNumberEl = document.getElementById('wa-qr-phone-number');

            // Starts as null (never a real signature, since even an empty
            // device list produces '') so the very first render always
            // runs and replaces the "Memuat device..." placeholder — even
            // when the user genuinely has zero devices yet.
            let renderedRowsSignature = null;
            let qrInstance = null;
            let renderedQrCode = null;
            let qrPollTimer = null;
            let tablePollTimer = null;

            function urlFor(template, id) {
                return template.replace('__ID__', id);
            }

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

            function timeLabel(iso) {
                if (!iso) return '-';
                const date = new Date(iso);
                if (isNaN(date.getTime()) || date.getFullYear() < 1971) return '-';
                return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
            }

            function statusMeta(status) {
                switch (status) {
                    case 'connected':
                        return { dot: 'wa-dot-connected', label: 'Terhubung', badge: 'bg-success-subtle text-success' };
                    case 'pending_qr':
                        return { dot: 'wa-dot-pending', label: 'Menunggu scan', badge: 'bg-warning-subtle text-warning' };
                    default:
                        return { dot: 'wa-dot-disconnected', label: 'Terputus', badge: 'bg-danger-subtle text-danger' };
                }
            }

            function renderDevices(devices) {
                const signature = devices.map(function (d) {
                    return d.id + ':' + d.status + ':' + d.phone_number + ':' + d.connected_at;
                }).join('|');

                if (signature === renderedRowsSignature) return;
                renderedRowsSignature = signature;

                tableBody.innerHTML = '';

                if (devices.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="5" class="text-center text-muted py-4">Belum ada device. Klik "Tambah Device" untuk memulai.</td>';
                    tableBody.appendChild(row);
                    return;
                }

                devices.forEach(function (device, index) {
                    const meta = statusMeta(device.status);
                    const row = document.createElement('tr');

                    const noCell = document.createElement('td');
                    noCell.textContent = index + 1;
                    noCell.dataset.label = 'No';

                    const phoneCell = document.createElement('td');
                    phoneCell.textContent = device.phone_number ? ('+' + device.phone_number) : '-';
                    phoneCell.dataset.label = 'No Handphone';

                    const statusCell = document.createElement('td');
                    statusCell.dataset.label = 'Status';
                    const badge = document.createElement('span');
                    badge.className = 'badge d-inline-flex align-items-center ' + meta.badge;
                    const dot = document.createElement('span');
                    dot.className = 'wa-status-dot ' + meta.dot;
                    badge.appendChild(dot);
                    badge.appendChild(document.createTextNode(meta.label));
                    statusCell.appendChild(badge);

                    const connectedCell = document.createElement('td');
                    connectedCell.textContent = timeLabel(device.connected_at);
                    connectedCell.className = 'text-muted small';
                    connectedCell.dataset.label = 'Terakhir Terhubung';

                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-end wa-action-cell';

                    // Not .btn-group — its joined-border layout can't
                    // wrap onto multiple lines, which is what was
                    // forcing this cell (and the whole table) wider than
                    // the screen on mobile. See .wa-action-buttons CSS
                    // above.
                    const actionGroup = document.createElement('div');
                    actionGroup.className = 'wa-action-buttons';

                    // Every action is now an icon-only "chip" of identical
                    // size (.wa-icon-btn, 34x34px) with a flat subtle-color
                    // background instead of an outlined text pill — same
                    // color per action as before, just simplified. `title`
                    // gives a native tooltip so the action is still
                    // discoverable without a text label.
                    function iconBtn(tag, colorClass, icon, title) {
                        const el = document.createElement(tag);
                        if (tag === 'button') el.type = 'button';
                        el.className = 'btn btn-sm wa-icon-btn ' + colorClass;
                        el.title = title;
                        el.setAttribute('aria-label', title);
                        el.innerHTML = '<i class="' + icon + '"></i>';
                        return el;
                    }

                    const inboxBtn = iconBtn('a', 'bg-primary-subtle text-primary', 'ri-message-3-line', 'Inbox');
                    if (device.status === 'connected') {
                        inboxBtn.href = urlFor(inboxUrlTemplate, device.id);
                    } else {
                        inboxBtn.classList.add('disabled');
                        inboxBtn.setAttribute('aria-disabled', 'true');
                        inboxBtn.href = '#';
                    }

                    const refreshBtn = iconBtn('button', 'bg-secondary-subtle text-secondary-emphasis', 'ri-refresh-line', 'Refresh');
                    refreshBtn.addEventListener('click', function () {
                        openQrModalForReconnect(device.id);
                    });

                    const disconnectBtn = iconBtn('button', 'bg-danger-subtle text-danger', 'ri-link-unlink-m', 'Disconnect');
                    disconnectBtn.disabled = device.status === 'disconnected';
                    disconnectBtn.addEventListener('click', function () {
                        disconnectDevice(device.id, disconnectBtn);
                    });

                    const apiKeyBtn = iconBtn('a', 'bg-dark-subtle text-dark-emphasis', 'ri-key-2-line', 'API Key');
                    let apiKeyHref = urlFor(apiKeyPageUrlTemplate, device.id);
                    if (device.phone_number) {
                        apiKeyHref += '?phone=' + encodeURIComponent(device.phone_number);
                    }
                    apiKeyBtn.href = apiKeyHref;

                    const historyBtn = iconBtn('button', 'bg-secondary-subtle text-secondary-emphasis', 'ri-history-line', 'Riwayat');
                    historyBtn.addEventListener('click', function () {
                        openHistoryModal(device.id, device.phone_number);
                    });

                    const healthBtn = iconBtn('button', 'bg-success-subtle text-success', 'ri-heart-pulse-line', 'Kesehatan');
                    healthBtn.addEventListener('click', function () {
                        openHealthModal(device.id, device.phone_number);
                    });

                    const flowBtn = iconBtn('a', 'bg-info-subtle text-info-emphasis', 'ri-git-branch-line', 'Chatbot Flow');
                    flowBtn.href = urlFor(chatbotFlowsUrlTemplate, device.id);

                    actionGroup.appendChild(inboxBtn);
                    actionGroup.appendChild(refreshBtn);
                    actionGroup.appendChild(apiKeyBtn);
                    actionGroup.appendChild(historyBtn);
                    actionGroup.appendChild(healthBtn);
                    actionGroup.appendChild(flowBtn);
                    actionGroup.appendChild(disconnectBtn);
                    actionCell.appendChild(actionGroup);

                    row.appendChild(noCell);
                    row.appendChild(phoneCell);
                    row.appendChild(statusCell);
                    row.appendChild(connectedCell);
                    row.appendChild(actionCell);
                    tableBody.appendChild(row);
                });
            }

            function loadDevices() {
                fetchJson(listUrl).then(function (data) {
                    renderDevices(data.devices || []);
                });
            }

            function disconnectDevice(id, btn) {
                if (!confirm('Putuskan device ini dari WhatsApp?')) return;

                btn.disabled = true;
                fetchJson(urlFor(disconnectUrlTemplate, id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).finally(function () {
                    renderedRowsSignature = ''; // force re-render
                    loadDevices();
                });
            }

            function renderQr(text) {
                if (!text || text === renderedQrCode) return;

                renderedQrCode = text;

                if (!qrInstance) {
                    qrContainer.innerHTML = '';
                    qrInstance = new QRCode(qrContainer, { text: text, width: 256, height: 256 });
                } else {
                    qrInstance.clear();
                    qrInstance.makeCode(text);
                }
            }

            function setQrBadge(text, className) {
                qrStatusBadge.textContent = text;
                qrStatusBadge.className = 'badge fs-13 px-3 py-2 ' + className;
            }

            function stopQrPolling() {
                if (qrPollTimer) {
                    clearInterval(qrPollTimer);
                    qrPollTimer = null;
                }
            }

            function resetQrModal() {
                renderedQrCode = null;
                qrInstance = null;
                qrContainer.innerHTML = '';
                qrWrapper.classList.remove('d-none');
                qrStatusBadge.classList.remove('d-none');
                qrConnectedInfo.classList.add('d-none');
                setQrBadge('Menunggu scan...', 'bg-warning-subtle text-warning');
            }

            function handleQrStatus(data) {
                if (data.error) {
                    setQrBadge('Gagal memuat status', 'bg-danger-subtle text-danger');
                    return;
                }

                switch (data.status) {
                    case 'connected':
                        stopQrPolling();
                        qrWrapper.classList.add('d-none');
                        qrStatusBadge.classList.add('d-none');
                        qrConnectedInfo.classList.remove('d-none');
                        qrPhoneNumberEl.textContent = data.phone_number ? ('+' + data.phone_number) : '';
                        renderedRowsSignature = ''; // force table re-render on next load
                        loadDevices();
                        break;

                    case 'pending_qr':
                        setQrBadge('Menunggu scan...', 'bg-warning-subtle text-warning');
                        renderQr(data.qr_string);
                        break;

                    default:
                        setQrBadge('Terputus, memuat ulang...', 'bg-danger-subtle text-danger');
                        break;
                }
            }

            function pollQrStatus(id) {
                fetchJson(urlFor(statusUrlTemplate, id))
                    .then(handleQrStatus)
                    .catch(function () {
                        setQrBadge('Gagal memuat status', 'bg-danger-subtle text-danger');
                    });
            }

            function openQrModalWith(promise, id) {
                resetQrModal();
                qrModal.show();

                promise
                    .then(handleQrStatus)
                    .catch(function () {
                        setQrBadge('Gagal memulai koneksi', 'bg-danger-subtle text-danger');
                    });

                stopQrPolling();
                qrPollTimer = setInterval(function () { pollQrStatus(id); }, 3000);
            }

            function openQrModalForAdd() {
                addDeviceBtn.disabled = true;
                fetchJson(addUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } })
                    .then(function (data) {
                        openQrModalWith(Promise.resolve(data), data.device_id);
                    })
                    .finally(function () {
                        addDeviceBtn.disabled = false;
                    });
            }

            function openQrModalForReconnect(id) {
                openQrModalWith(
                    fetchJson(urlFor(reconnectUrlTemplate, id), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } }),
                    id
                );
            }

            qrModalEl.addEventListener('hidden.bs.modal', function () {
                stopQrPolling();
                renderedRowsSignature = ''; // force table re-render in case status changed
                loadDevices();
            });

            addDeviceBtn.addEventListener('click', openQrModalForAdd);

            loadDevices();
            tablePollTimer = setInterval(loadDevices, 5000);

            // --- history modal --------------------------------------------
            const historyModalEl = document.getElementById('wa-history-modal');
            const historyModal = new bootstrap.Modal(historyModalEl);
            const historyPhoneEl = document.getElementById('wa-history-phone');
            const historyLoadingEl = document.getElementById('wa-history-loading');
            const historyEmptyEl = document.getElementById('wa-history-empty');
            const historyListEl = document.getElementById('wa-history-list');

            const HISTORY_META = {
                connected: { label: 'Terhubung', color: '#22c55e' },
                reconnected: { label: 'Berhasil Tersambung Ulang', color: '#22c55e' },
                disconnected: { label: 'Terputus Sementara', color: '#f59e0b' },
                reconnecting: { label: 'Mencoba Menyambung Ulang', color: '#f59e0b' },
                pending_qr: { label: 'Menunggu Scan QR', color: '#f59e0b' },
                reconnect_failed: { label: 'Gagal Menyambung Ulang', color: '#ef4444' },
                logged_out: { label: 'Logout dari WhatsApp', color: '#ef4444' },
                manual_disconnect: { label: 'Diputuskan Manual', color: '#6b7280' },
            };

            function historyTimeLabel(iso) {
                if (!iso) return '-';
                const date = new Date(iso);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
            }

            function renderHistory(items) {
                historyListEl.innerHTML = '';
                historyEmptyEl.classList.toggle('d-none', items.length > 0);

                items.forEach(function (item) {
                    const meta = HISTORY_META[item.event] || { label: item.event, color: '#9ca3af' };

                    const li = document.createElement('li');
                    li.className = 'wa-history-item';

                    const dot = document.createElement('span');
                    dot.className = 'wa-history-dot';
                    dot.style.background = meta.color;
                    li.appendChild(dot);

                    const body = document.createElement('div');

                    const label = document.createElement('div');
                    label.className = 'wa-history-label';
                    label.textContent = meta.label;
                    body.appendChild(label);

                    if (item.detail) {
                        const detail = document.createElement('div');
                        detail.className = 'wa-history-detail';
                        detail.textContent = item.detail;
                        body.appendChild(detail);
                    }

                    const time = document.createElement('div');
                    time.className = 'wa-history-time';
                    time.textContent = historyTimeLabel(item.created_at);
                    body.appendChild(time);

                    li.appendChild(body);
                    historyListEl.appendChild(li);
                });
            }

            function loadHistory(deviceId) {
                historyLoadingEl.classList.remove('d-none');
                historyEmptyEl.classList.add('d-none');
                historyListEl.innerHTML = '';

                fetchJson(urlFor(historyUrlTemplate, deviceId))
                    .then(function (data) {
                        renderHistory(data.history || []);
                    })
                    .catch(function () {
                        historyEmptyEl.textContent = 'Gagal memuat riwayat.';
                        historyEmptyEl.classList.remove('d-none');
                    })
                    .finally(function () {
                        historyLoadingEl.classList.add('d-none');
                    });
            }

            function openHistoryModal(deviceId, phoneNumber) {
                historyPhoneEl.textContent = phoneNumber ? ('— +' + phoneNumber) : '';
                historyModal.show();
                loadHistory(deviceId);
            }

            // --- health modal -----------------------------------------
            const healthModalEl = document.getElementById('wa-health-modal');
            const healthModal = new bootstrap.Modal(healthModalEl);
            const healthPhoneEl = document.getElementById('wa-health-phone');
            const healthLoadingEl = document.getElementById('wa-health-loading');
            const healthContentEl = document.getElementById('wa-health-content');
            const healthScoreCircleEl = document.getElementById('wa-health-score-circle');
            const healthScoreNumberEl = document.getElementById('wa-health-score-number');
            const healthLabelBadgeEl = document.getElementById('wa-health-label-badge');

            const HEALTH_COLOR = {
                'Sehat': { circle: 'bg-success-subtle text-success', badge: 'bg-success-subtle text-success' },
                'Perlu Perhatian': { circle: 'bg-warning-subtle text-warning', badge: 'bg-warning-subtle text-warning' },
                'Berisiko': { circle: 'bg-danger-subtle text-danger', badge: 'bg-danger-subtle text-danger' },
            };

            function renderHealth(health) {
                const color = HEALTH_COLOR[health.label] || HEALTH_COLOR['Berisiko'];

                healthScoreCircleEl.className = 'avatar-item avatar-lg rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + color.circle;
                healthScoreNumberEl.textContent = health.score;
                healthLabelBadgeEl.className = 'badge fs-13 px-3 py-2 ' + color.badge;
                healthLabelBadgeEl.textContent = health.label + (health.status !== 'connected' ? ' — Sedang Terputus' : '');

                document.getElementById('wa-health-logged-out').textContent = health.signals.logged_out_count + 'x';
                document.getElementById('wa-health-disconnected').textContent = health.signals.disconnected_count + 'x';
                document.getElementById('wa-health-reconnect-failed').textContent = health.signals.reconnect_failed_count + 'x';
                document.getElementById('wa-health-failure-rate').textContent = health.signals.send_failure_rate !== null
                    ? (health.signals.send_failure_rate + '%')
                    : 'Belum ada data';
            }

            function loadHealth(deviceId) {
                healthLoadingEl.textContent = 'Memuat...';
                healthLoadingEl.classList.remove('d-none');
                healthContentEl.classList.add('d-none');

                fetchJson(urlFor(healthUrlTemplate, deviceId))
                    .then(function (data) {
                        renderHealth(data.health);
                        healthLoadingEl.classList.add('d-none');
                        healthContentEl.classList.remove('d-none');
                    })
                    .catch(function () {
                        healthLoadingEl.textContent = 'Gagal memuat data kesehatan.';
                    });
            }

            function openHealthModal(deviceId, phoneNumber) {
                healthPhoneEl.textContent = phoneNumber ? ('— +' + phoneNumber) : '';
                healthModal.show();
                loadHealth(deviceId);
            }
        })();
        });
    </script>
@endsection
