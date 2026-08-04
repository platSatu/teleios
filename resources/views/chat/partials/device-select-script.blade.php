{{--
    Shared device dropdown populator, included by every WhatsApp
    automation page that needs a "device_id" select. There's no local
    wa_devices table to loop over server-side (devices live in the Go
    backend — see App\Services\Chat\ConnectDeviceService), so every
    `<select class="wa-device-select">` on the page is populated
    client-side from the same chat.connect-device.list endpoint the
    Device/Inbox page already uses.

    Usage on a <select>:
      <select class="wa-device-select form-select" name="device_id"
              data-selected="{{ $schedule->device_id ?? '' }}">
          <option value="">Memuat device...</option>
      </select>

    Add data-only-connected="1" on a select to only offer devices whose
    status is "connected" (used by the AI Bot form, where a bot can only
    be attached to a device that's actually online right now).
--}}
<script>
    (function () {
        var listUrl = @json(route('chat.connect-device.list'));

        fetch(listUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var devices = data.devices || [];

                document.querySelectorAll('.wa-device-select').forEach(function (select) {
                    var selected = select.getAttribute('data-selected') || '';
                    var onlyConnected = select.getAttribute('data-only-connected') === '1';
                    var list = onlyConnected
                        ? devices.filter(function (d) { return d.status === 'connected'; })
                        : devices;

                    select.innerHTML = '';

                    if (list.length === 0) {
                        var empty = document.createElement('option');
                        empty.value = '';
                        empty.textContent = onlyConnected ? 'Tidak ada device yang terhubung' : 'Belum ada device';
                        select.appendChild(empty);
                        return;
                    }

                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '-- Pilih Device --';
                    select.appendChild(placeholder);

                    list.forEach(function (device) {
                        var option = document.createElement('option');
                        option.value = device.id;
                        option.textContent = '+' + device.phone_number + (device.status === 'connected' ? ' (Terhubung)' : ' (' + device.status + ')');
                        if (device.id === selected) option.selected = true;
                        select.appendChild(option);
                    });
                });
            })
            .catch(function () {
                document.querySelectorAll('.wa-device-select').forEach(function (select) {
                    select.innerHTML = '<option value="">Gagal memuat device</option>';
                });
            });
    })();
</script>
