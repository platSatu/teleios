@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">WA Group</h4>
                        <p class="text-muted mb-0">Daftar grup WhatsApp live dari device yang terhubung — pilih device untuk memuat.</p>
                    </div>
                </div>

                <div class="mb-3" style="max-width: 320px;">
                    <label class="form-label">Device</label>
                    <select class="wa-device-select form-select" data-only-connected="1">
                        <option value="">Memuat device...</option>
                    </select>
                </div>

                <div id="waGroupList">
                    <p class="text-muted small mb-0">Pilih device di atas untuk memuat daftar grup.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')

<script>
    (function () {
        var deviceSelect = document.querySelector('.wa-device-select');
        var listEl = document.getElementById('waGroupList');
        var chatsUrlTemplate = {!! json_encode(route('inbox.chats', ['device' => '__DEVICEID__'])) !!};
        var GROUP_JID_SUFFIX = '@' + 'g.us';

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function loadGroups(deviceId) {
            if (!deviceId) {
                listEl.innerHTML = '<p class="text-muted small mb-0">Pilih device di atas untuk memuat daftar grup.</p>';
                return;
            }

            listEl.innerHTML = '<p class="text-muted small mb-0">Memuat grup WhatsApp...</p>';

            fetch(chatsUrlTemplate.replace('__DEVICEID__', deviceId), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var groups = (data.chats || []).filter(function (c) {
                        return typeof c.chat_jid === 'string' && c.chat_jid.indexOf(GROUP_JID_SUFFIX) !== -1;
                    });

                    if (groups.length === 0) {
                        listEl.innerHTML = '<p class="text-muted small mb-0">Tidak ada grup WhatsApp pada device ini.</p>';
                        return;
                    }

                    var rows = groups.map(function (group) {
                        var name = escapeHtml(group.name || group.chat_jid);
                        var jid = escapeHtml(group.chat_jid);
                        var lastMessage = escapeHtml(group.last_message || '-');

                        return '' +
                            '<tr>' +
                                '<td class="fw-semibold">' + name + '</td>' +
                                '<td><code class="small">' + jid + '</code></td>' +
                                '<td class="text-truncate" style="max-width: 260px;">' + lastMessage + '</td>' +
                                '<td class="text-end">' +
                                    '<button type="button" class="btn btn-sm btn-light js-copy-jid" data-jid="' + jid + '">' +
                                        '<i class="ri-file-copy-line"></i> Salin JID' +
                                    '</button>' +
                                '</td>' +
                            '</tr>';
                    }).join('');

                    listEl.innerHTML = '' +
                        '<div class="table-responsive">' +
                            '<table class="table table-centered table-hover align-middle mb-0">' +
                                '<thead class="table-light"><tr>' +
                                    '<th>Nama Grup</th><th>JID</th><th>Pesan Terakhir</th><th class="text-end">Aksi</th>' +
                                '</tr></thead>' +
                                '<tbody>' + rows + '</tbody>' +
                            '</table>' +
                        '</div>';

                    listEl.querySelectorAll('.js-copy-jid').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            navigator.clipboard.writeText(btn.getAttribute('data-jid')).then(function () {
                                var original = btn.innerHTML;
                                btn.innerHTML = '<i class="ri-check-line"></i> Tersalin';
                                setTimeout(function () { btn.innerHTML = original; }, 1500);
                            });
                        });
                    });
                })
                .catch(function () {
                    listEl.innerHTML = '<p class="text-danger small mb-0">Gagal memuat grup WhatsApp.</p>';
                });
        }

        // The shared device-select-script populates the <select> async —
        // poll briefly for it to finish, then wire the change listener.
        var waitForOptions = setInterval(function () {
            if (deviceSelect.options.length > 0 && deviceSelect.options[0].value !== '' || deviceSelect.options.length > 1) {
                clearInterval(waitForOptions);
                deviceSelect.addEventListener('change', function () {
                    loadGroups(deviceSelect.value);
                });
            }
        }, 200);
        setTimeout(function () { clearInterval(waitForOptions); }, 5000);
    })();
</script>
@endsection
