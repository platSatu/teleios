@extends('layouts.dashboard')

@section('content')
@php
    $webhookUrl = route('third-party.google-form.receive', $integration->webhook_token);
    $appsScript = <<<JS
function onFormSubmit(e) {
  var CONFIG = {
    webhookUrl: "{$webhookUrl}"
  };

  // Setiap jawaban dikirim sebagai satu objek JSON, memakai judul
  // pertanyaan sebagai key -- persis field "Nama Field Nomor WhatsApp"
  // yang kamu isi di halaman integrasi ini.
  var data = {};
  e.response.getItemResponses().forEach(function (item) {
    data[item.getItem().getTitle()] = item.getResponse();
  });

  var options = {
    method: "post",
    contentType: "application/json",
    payload: JSON.stringify(data),
    muteHttpExceptions: true
  };

  var response = UrlFetchApp.fetch(CONFIG.webhookUrl, options);
  // Terlihat di Apps Script > Executions kalau pengiriman gagal.
  Logger.log("Webhook response %s: %s", response.getResponseCode(), response.getContentText());
}
JS;
@endphp
<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="{{ route('chat.third-party.google-form.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h4 class="mb-0">{{ $integration->name }}</h4>
                <p class="text-muted mb-0 small">
                    Device: <span id="gform-device-label" data-device-id="{{ $integration->device_id }}">memeriksa&hellip;</span> &middot;
                    WA Template: {{ $integration->waMessageTemplate->name ?? '(belum dipilih)' }} &middot;
                    <span class="badge {{ $integration->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $integration->status }}</span>
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Populated/shown by the script at the bottom once the device
             list comes back — covers exactly the case the user reported:
             a submission failing with "pastikan device masih terhubung"
             gives no way to tell, from this page, whether that's actually
             true. This turns it into an explicit, actionable banner
             instead of something only visible per-submission in the
             table on the right. --}}
        <div id="gform-device-warning" class="alert alert-danger d-none mb-3"></div>

        <div class="row">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <p class="fw-semibold mb-1">Webhook URL</p>
                        <p class="text-muted small mb-2">
                            Sudah tertanam di script Apps Script di bawah &mdash; kamu tidak perlu menempelnya secara
                            terpisah, ini hanya untuk referensi.
                        </p>
                        <div class="input-group input-group-sm mb-3">
                            <input type="text" class="form-control" id="gform-webhook-url" value="{{ $webhookUrl }}" readonly>
                            <button type="button" class="btn btn-outline-secondary wa-copy-btn" data-target="gform-webhook-url">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </div>

                        <p class="fw-semibold mb-1">Cara pasang</p>
                        <ol class="text-muted small mb-3 ps-3">
                            <li>Buka Google Form kamu &rarr; menu <strong>Extensions</strong> &rarr; <strong>Apps Script</strong>.</li>
                            <li>Hapus isi editor, tempel script di bawah.</li>
                            <li>Klik ikon jam (Triggers) &rarr; Add Trigger &rarr; pilih fungsi <code>onFormSubmit</code>, event type <strong>On form submit</strong>, lalu simpan (Google akan minta izin akses form kamu).</li>
                            <li>Isi form-nya sekali untuk uji coba &mdash; hasilnya akan muncul di tabel "Submission Terakhir" di bawah.</li>
                        </ol>

                        <label class="form-label fw-semibold mb-1">Script Google Apps Script</label>
                        <textarea class="form-control form-control-sm" id="gform-apps-script" rows="14" readonly
                            style="font-family: monospace; font-size: 11px;">{{ $appsScript }}</textarea>
                        <div class="text-end mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm wa-copy-btn" data-target="gform-apps-script">
                                <i class="ri-file-copy-line"></i> Salin Script
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body">
                        <p class="fw-semibold mb-2">Keamanan</p>
                        <p class="text-muted small mb-0">
                            URL webhook ini adalah satu-satunya kunci akses &mdash; siapa pun yang memegangnya bisa
                            memicu pengiriman WhatsApp lewat device di atas. Kalau URL ini pernah ter-share ke tempat
                            publik, regenerate segera.
                        </p>
                        <form action="{{ route('chat.third-party.google-form.regenerate-token', $integration->id) }}" method="POST" class="mt-2"
                            onsubmit="return confirm('Generate ulang Webhook URL? URL lama langsung berhenti berfungsi, dan script di Google Form harus diperbarui.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="ri-refresh-line"></i> Regenerate Webhook URL
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body">
                        <p class="fw-semibold mb-3">Submission Terakhir</p>

                        <div class="table-responsive">
                            <table class="table table-sm table-centered align-middle mb-0" style="min-width: 480px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Nomor Tujuan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($submissions as $submission)
                                        <tr>
                                            <td class="small">{{ $submission->created_at->format('d M Y H:i') }}</td>
                                            <td class="small">{{ $submission->target_number ?: '-' }}</td>
                                            <td>
                                                @if ($submission->status === 'sent')
                                                    <span class="badge bg-success-subtle text-success">Terkirim</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger" title="{{ $submission->error_message }}">Gagal</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($submission->status === 'failed' && $submission->error_message)
                                            <tr>
                                                <td colspan="3" class="small text-danger pt-0 pb-2">{{ $submission->error_message }}</td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">
                                                Belum ada submission masuk. Coba isi form kamu sekali untuk menguji koneksinya.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">{{ $submissions->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.wa-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target || !target.value) return;

            navigator.clipboard.writeText(target.value).then(function () {
                var icon = btn.querySelector('i');
                var original = icon.className;
                icon.className = 'ri-check-line text-success';
                setTimeout(function () { icon.className = original; }, 1200);
            });
        });
    });

    // Resolves the device_id stored on this integration to a readable
    // "+62812... (Terhubung)" label, and surfaces #gform-device-warning
    // when the device is missing entirely (deleted, or moved to another
    // company) or currently disconnected — both silently produce the
    // exact "Gagal mengirim pesan. Pastikan device masih terhubung."
    // failure on every submission, so this makes the real cause visible
    // on the page itself instead of only inside error_message per-row.
    (function () {
        var label = document.getElementById('gform-device-label');
        var warning = document.getElementById('gform-device-warning');
        if (!label) return;

        var deviceId = label.getAttribute('data-device-id');
        var listUrl = @json(route('chat.connect-device.list'));
        var connectDeviceUrl = @json(route('chat.connect-device.index'));
        var editUrl = @json(route('chat.third-party.google-form.edit', $integration->id));

        fetch(listUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var devices = data.devices || [];
                var match = null;
                for (var i = 0; i < devices.length; i++) {
                    if (devices[i].id === deviceId) { match = devices[i]; break; }
                }

                if (!match) {
                    label.textContent = 'tidak ditemukan';
                    label.className = 'text-danger fw-semibold';
                    warning.innerHTML = '<i class="ri-error-warning-line"></i> Device pengirim integrasi ini tidak ditemukan &mdash; kemungkinan sudah dihapus atau dipindah ke company lain. Setiap submission form akan gagal terkirim sampai ini diperbaiki. <a href="' + editUrl + '" class="alert-link">Pilih ulang device di halaman Edit</a>.';
                    warning.classList.remove('d-none');
                    return;
                }

                var connected = match.status === 'connected';
                label.textContent = '+' + match.phone_number + (connected ? ' (Terhubung)' : ' (' + match.status + ')');

                if (!connected) {
                    label.className = 'text-warning fw-semibold';
                    warning.innerHTML = '<i class="ri-error-warning-line"></i> Device pengirim (+' + match.phone_number + ') sedang tidak terhubung ke WhatsApp, jadi balasan form ini akan gagal terkirim. <a href="' + connectDeviceUrl + '" class="alert-link">Sambungkan ulang di menu Connect Device</a>.';
                    warning.classList.remove('d-none');
                }
            })
            .catch(function () {
                label.textContent = 'gagal memuat status';
            });
    })();
</script>
@endsection
