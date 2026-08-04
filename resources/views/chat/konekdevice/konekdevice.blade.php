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
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">No</th>
                                    <th>No Handphone</th>
                                    <th>Status</th>
                                    <th>Terakhir Terhubung</th>
                                    <th class="text-end">Action</th>
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

    {{-- API Key modal — token/secret_key a third party uses to send
         messages through one specific device (e.g. as a notification
         channel), without ever logging into this dashboard. See
         App\Http\Controllers\Chat\WaApiKeyController and
         App\Models\WaApiKey. Shared by every row's "API Key" button. --}}
    <div class="modal fade" id="wa-api-key-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">API Key Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Dipakai pihak ketiga (mis. sistem lain) untuk <strong>mengirim pesan</strong> lewat device ini,
                        tanpa perlu login ke dashboard. Lihat dokumentasi lengkap di
                        <a href="{{ url('/dokumentasi') }}" target="_blank" rel="noopener">/dokumentasi</a>.
                    </p>

                    <div id="wa-api-key-loading" class="text-center text-muted py-4">Memuat...</div>

                    <div id="wa-api-key-empty" class="d-none text-center py-3">
                        <p class="text-muted mb-3">Device ini belum punya API Key.</p>
                        <button type="button" id="wa-api-key-generate-btn" class="btn btn-primary btn-sm">
                            <i class="ri-key-2-line"></i> Generate Token &amp; Secret Key
                        </button>
                    </div>

                    <div id="wa-api-key-details" class="d-none">
                        <div class="mb-2">
                            <label class="form-label fw-semibold mb-1">API Host</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="wa-api-key-host" readonly>
                                <button type="button" class="btn btn-outline-secondary wa-copy-btn" data-target="wa-api-key-host"><i class="ri-file-copy-line"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold mb-1">Token</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="wa-api-key-token" readonly>
                                <button type="button" class="btn btn-outline-secondary wa-copy-btn" data-target="wa-api-key-token"><i class="ri-file-copy-line"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold mb-1">Secret Key</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="wa-api-key-secret" readonly>
                                <button type="button" class="btn btn-outline-secondary wa-copy-btn" data-target="wa-api-key-secret"><i class="ri-file-copy-line"></i></button>
                            </div>
                        </div>
                        <p class="text-muted fs-12 mb-3">Terakhir dipakai: <span id="wa-api-key-last-used">-</span></p>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" id="wa-api-key-regen-token-btn" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-refresh-line"></i> Regenerate Token
                            </button>
                            <button type="button" id="wa-api-key-regen-secret-btn" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-refresh-line"></i> Regenerate Secret Key
                            </button>
                        </div>
                        <p class="text-danger fs-12 mt-2 mb-0">
                            Regenerate langsung mematikan token/secret lama — perbarui juga di pihak ketiga yang memakainya.
                        </p>
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
            const listUrl = @json(route('chat.connect-device.list'));
            const addUrl = @json(route('chat.connect-device.add'));
            const statusUrlTemplate = @json(route('chat.connect-device.status', ['device' => '__ID__']));
            const reconnectUrlTemplate = @json(route('chat.connect-device.reconnect', ['device' => '__ID__']));
            const disconnectUrlTemplate = @json(route('chat.connect-device.disconnect', ['device' => '__ID__']));
            const inboxUrlTemplate = @json(route('inbox.index', ['device' => '__ID__']));
            const apiKeyShowUrlTemplate = @json(route('chat.connect-device.api-key.show', ['device' => '__ID__']));
            const apiKeyGenerateUrlTemplate = @json(route('chat.connect-device.api-key.generate', ['device' => '__ID__']));
            const apiKeyRegenTokenUrlTemplate = @json(route('chat.connect-device.api-key.regenerate-token', ['device' => '__ID__']));
            const apiKeyRegenSecretUrlTemplate = @json(route('chat.connect-device.api-key.regenerate-secret', ['device' => '__ID__']));
            const csrfToken = @json(csrf_token());

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
                return fetch(url, Object.assign({ headers: { 'Accept': 'application/json' } }, options))
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

                    const phoneCell = document.createElement('td');
                    phoneCell.textContent = device.phone_number ? ('+' + device.phone_number) : '-';

                    const statusCell = document.createElement('td');
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

                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-end';

                    const actionGroup = document.createElement('div');
                    actionGroup.className = 'btn-group btn-group-sm';

                    const inboxBtn = document.createElement('a');
                    inboxBtn.className = 'btn btn-outline-primary';
                    inboxBtn.innerHTML = '<i class="ri-message-3-line"></i> Inbox';
                    if (device.status === 'connected') {
                        inboxBtn.href = urlFor(inboxUrlTemplate, device.id);
                    } else {
                        inboxBtn.classList.add('disabled');
                        inboxBtn.setAttribute('aria-disabled', 'true');
                        inboxBtn.href = '#';
                    }

                    const refreshBtn = document.createElement('button');
                    refreshBtn.type = 'button';
                    refreshBtn.className = 'btn btn-outline-secondary';
                    refreshBtn.innerHTML = '<i class="ri-refresh-line"></i> Refresh';
                    refreshBtn.addEventListener('click', function () {
                        openQrModalForReconnect(device.id);
                    });

                    const disconnectBtn = document.createElement('button');
                    disconnectBtn.type = 'button';
                    disconnectBtn.className = 'btn btn-outline-danger';
                    disconnectBtn.innerHTML = '<i class="ri-link-unlink-m"></i> Disconnect';
                    disconnectBtn.disabled = device.status === 'disconnected';
                    disconnectBtn.addEventListener('click', function () {
                        disconnectDevice(device.id, disconnectBtn);
                    });

                    const apiKeyBtn = document.createElement('button');
                    apiKeyBtn.type = 'button';
                    apiKeyBtn.className = 'btn btn-outline-dark';
                    apiKeyBtn.innerHTML = '<i class="ri-key-2-line"></i> API Key';
                    apiKeyBtn.addEventListener('click', function () {
                        openApiKeyModal(device.id, device.phone_number);
                    });

                    actionGroup.appendChild(inboxBtn);
                    actionGroup.appendChild(refreshBtn);
                    actionGroup.appendChild(apiKeyBtn);
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

            // --- API Key modal -------------------------------------------------
            const apiKeyModalEl = document.getElementById('wa-api-key-modal');
            const apiKeyModal = new bootstrap.Modal(apiKeyModalEl);
            const apiKeyLoading = document.getElementById('wa-api-key-loading');
            const apiKeyEmpty = document.getElementById('wa-api-key-empty');
            const apiKeyDetails = document.getElementById('wa-api-key-details');
            const apiKeyGenerateBtn = document.getElementById('wa-api-key-generate-btn');
            const apiKeyRegenTokenBtn = document.getElementById('wa-api-key-regen-token-btn');
            const apiKeyRegenSecretBtn = document.getElementById('wa-api-key-regen-secret-btn');
            const apiKeyHostInput = document.getElementById('wa-api-key-host');
            const apiKeyTokenInput = document.getElementById('wa-api-key-token');
            const apiKeySecretInput = document.getElementById('wa-api-key-secret');
            const apiKeyLastUsedEl = document.getElementById('wa-api-key-last-used');

            let currentApiKeyDeviceId = null;

            function apiKeyShowState(state) {
                apiKeyLoading.classList.toggle('d-none', state !== 'loading');
                apiKeyEmpty.classList.toggle('d-none', state !== 'empty');
                apiKeyDetails.classList.toggle('d-none', state !== 'details');
            }

            function renderApiKey(apiKey) {
                if (!apiKey) {
                    apiKeyShowState('empty');
                    return;
                }

                apiKeyHostInput.value = apiKey.api_host || '';
                apiKeyTokenInput.value = apiKey.token || '';
                apiKeySecretInput.value = apiKey.secret_key || '';
                apiKeyLastUsedEl.textContent = apiKey.last_used_at || 'Belum pernah dipakai';
                apiKeyShowState('details');
            }

            function loadApiKey(deviceId) {
                apiKeyShowState('loading');
                fetchJson(urlFor(apiKeyShowUrlTemplate, deviceId))
                    .then(function (data) { renderApiKey(data.api_key); })
                    .catch(function () { apiKeyShowState('empty'); });
            }

            function openApiKeyModal(deviceId, phoneNumber) {
                currentApiKeyDeviceId = deviceId;
                apiKeyModal.show();
                loadApiKey(deviceId);

                apiKeyGenerateBtn.onclick = function () {
                    apiKeyGenerateBtn.disabled = true;
                    const url = urlFor(apiKeyGenerateUrlTemplate, deviceId);
                    const body = phoneNumber ? ('device_label=' + encodeURIComponent('+' + phoneNumber)) : '';
                    fetchJson(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: body,
                    })
                        .then(function (data) { renderApiKey(data.api_key); })
                        .finally(function () { apiKeyGenerateBtn.disabled = false; });
                };
            }

            function regenerateApiKeyPart(urlTemplate, confirmMessage) {
                if (!currentApiKeyDeviceId) return;
                if (!confirm(confirmMessage)) return;

                fetchJson(urlFor(urlTemplate, currentApiKeyDeviceId), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).then(function (data) { renderApiKey(data.api_key); });
            }

            apiKeyRegenTokenBtn.addEventListener('click', function () {
                regenerateApiKeyPart(apiKeyRegenTokenUrlTemplate, 'Generate ulang Token? Token lama langsung berhenti berfungsi.');
            });

            apiKeyRegenSecretBtn.addEventListener('click', function () {
                regenerateApiKeyPart(apiKeyRegenSecretUrlTemplate, 'Generate ulang Secret Key? Secret lama langsung berhenti berfungsi.');
            });

            document.querySelectorAll('.wa-copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = document.getElementById(btn.getAttribute('data-target'));
                    if (!input || !input.value) return;

                    navigator.clipboard.writeText(input.value).then(function () {
                        const icon = btn.querySelector('i');
                        const original = icon.className;
                        icon.className = 'ri-check-line text-success';
                        setTimeout(function () { icon.className = original; }, 1200);
                    });
                });
            });
        })();
        });
    </script>
@endsection
