@extends('layouts.dashboard')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('chat.connect-device.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali ke daftar device">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h4 class="mb-0">API Key Device</h4>
                    <p class="text-muted mb-0 small" id="wa-api-key-phone-label">
                        @if ($devicePhone)
                            +{{ $devicePhone }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
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

                        <hr class="my-3">

                        {{-- The old "Feedback dari Google Form ke WhatsApp" script
                             generator that used to live here has moved to its own
                             proper integration under Chat > Third Party > Google
                             Form (App\Models\WaFormIntegration) — a real saved
                             config (name, target-number field, WA Template reply)
                             instead of a one-off client-side script tied to
                             whatever was typed into this page at the time. --}}
                        <p class="fw-semibold mb-1">Feedback dari Google Form ke WhatsApp</p>
                        <p class="text-muted fs-12 mb-0">
                            Fitur ini sudah pindah ke menu <strong>Third Party &rarr; Google Form</strong> di sidebar
                            Chat — di sana kamu bisa membuat integrasi bernama, memilih WA Template sebagai balasan,
                            dan melihat log setiap submission yang masuk.
                            <a href="{{ route('chat.third-party.google-form.index') }}">Buka Third Party &rarr; Google Form</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const deviceId = @json($deviceId);
            const devicePhone = @json($devicePhone);
            const apiKeyDataUrl = @json(route('chat.connect-device.api-key.data', ['device' => $deviceId]));
            const apiKeyGenerateUrl = @json(route('chat.connect-device.api-key.generate', ['device' => $deviceId]));
            const apiKeyRegenTokenUrl = @json(route('chat.connect-device.api-key.regenerate-token', ['device' => $deviceId]));
            const apiKeyRegenSecretUrl = @json(route('chat.connect-device.api-key.regenerate-secret', ['device' => $deviceId]));
            const csrfToken = @json(csrf_token());

            function fetchJson(url, options) {
                return fetch(url, Object.assign({ headers: { 'Accept': 'application/json' } }, options))
                    .then(function (res) { return res.json(); });
            }

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

            function loadApiKey() {
                apiKeyShowState('loading');
                fetchJson(apiKeyDataUrl)
                    .then(function (data) { renderApiKey(data.api_key); })
                    .catch(function () { apiKeyShowState('empty'); });
            }

            apiKeyGenerateBtn.addEventListener('click', function () {
                apiKeyGenerateBtn.disabled = true;
                const body = devicePhone ? ('device_label=' + encodeURIComponent('+' + devicePhone)) : '';
                fetchJson(apiKeyGenerateUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body,
                })
                    .then(function (data) { renderApiKey(data.api_key); })
                    .finally(function () { apiKeyGenerateBtn.disabled = false; });
            });

            function regenerateApiKeyPart(url, confirmMessage) {
                if (!confirm(confirmMessage)) return;

                fetchJson(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).then(function (data) { renderApiKey(data.api_key); });
            }

            apiKeyRegenTokenBtn.addEventListener('click', function () {
                regenerateApiKeyPart(apiKeyRegenTokenUrl, 'Generate ulang Token? Token lama langsung berhenti berfungsi.');
            });

            apiKeyRegenSecretBtn.addEventListener('click', function () {
                regenerateApiKeyPart(apiKeyRegenSecretUrl, 'Generate ulang Secret Key? Secret lama langsung berhenti berfungsi.');
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

            loadApiKey();
        })();
        });
    </script>
@endsection
