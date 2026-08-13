@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Laporan Chat</h4>
                        <p class="text-muted mb-0">Respon &amp; penyelesaian, performa agent, broadcast, dan kepuasan pelanggan (CSAT).</p>
                    </div>
                    <form class="d-flex flex-wrap align-items-center gap-2" onsubmit="return false;">
                        <input type="date" id="wa-report-from" class="form-control" style="max-width: 165px;">
                        <span class="text-muted">s/d</span>
                        <input type="date" id="wa-report-to" class="form-control" style="max-width: 165px;">
                        <button type="button" id="wa-report-apply" class="btn btn-primary"><i class="ri-filter-3-line"></i> Terapkan</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Respon & Penyelesaian --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-primary-subtle text-primary flex-shrink-0"><i class="ri-chat-3-line fs-4"></i></div>
                        <div>
                            <div class="text-muted small">Total Percakapan</div>
                            <h4 class="mb-0" id="wa-rep-total">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-success-subtle text-success flex-shrink-0"><i class="ri-check-double-line fs-4"></i></div>
                        <div>
                            <div class="text-muted small">Selesai</div>
                            <h4 class="mb-0" id="wa-rep-resolved">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-info-subtle text-info flex-shrink-0"><i class="ri-timer-flash-line fs-4"></i></div>
                        <div>
                            <div class="text-muted small">Rata² Respon Pertama</div>
                            <h4 class="mb-0" id="wa-rep-avg-first">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-warning-subtle text-warning flex-shrink-0"><i class="ri-alarm-warning-line fs-4"></i></div>
                        <div>
                            <div class="text-muted small">SLA Terlambat</div>
                            <h4 class="mb-0" id="wa-rep-breach-rate">-</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Agent performance --}}
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Performa Agent</h5>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover align-middle mb-0" style="min-width: 560px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 160px;">Agent</th>
                                        <th style="min-width: 90px;">Ditangani</th>
                                        <th style="min-width: 90px;">Selesai</th>
                                        <th style="min-width: 110px;">Rata Respon</th>
                                        <th style="min-width: 90px;">Terlambat</th>
                                    </tr>
                                </thead>
                                <tbody id="wa-rep-agents-body">
                                    <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Broadcast --}}
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Broadcast (Pesan Terjadwal)</h5>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-primary-subtle text-primary fs-13 px-3 py-2">Total: <span id="wa-rep-bc-total">-</span></span>
                            <span class="badge bg-success-subtle text-success fs-13 px-3 py-2">Delivered: <span id="wa-rep-bc-delivered">-</span></span>
                            <span class="badge bg-info-subtle text-info fs-13 px-3 py-2">Read: <span id="wa-rep-bc-read">-</span></span>
                            <span class="badge bg-danger-subtle text-danger fs-13 px-3 py-2">Gagal: <span id="wa-rep-bc-failed">-</span></span>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between fs-12 text-muted mb-1">
                                <span>Delivery Rate</span><span id="wa-rep-bc-delivery-rate">-</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="wa-rep-bc-delivery-bar" style="width:0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between fs-12 text-muted mb-1">
                                <span>Read Rate</span><span id="wa-rep-bc-read-rate">-</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" id="wa-rep-bc-read-bar" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-0">
            {{-- CSAT --}}
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Kepuasan Pelanggan (CSAT)</h5>
                        <div class="d-flex align-items-center gap-4 mb-3 flex-wrap">
                            <div>
                                <div class="text-muted small">Rata² Skor</div>
                                <h3 class="mb-0" id="wa-rep-csat-score">-</h3>
                            </div>
                            <div>
                                <div class="text-muted small">Survei Terkirim</div>
                                <h5 class="mb-0" id="wa-rep-csat-sent">-</h5>
                            </div>
                            <div>
                                <div class="text-muted small">Response Rate</div>
                                <h5 class="mb-0" id="wa-rep-csat-rate">-</h5>
                            </div>
                        </div>
                        <div id="wa-rep-csat-distribution"></div>
                        <p class="text-muted fs-12 mt-2 mb-0">Aktifkan survei CSAT otomatis di <a href="{{ route('chat.settings.edit') }}">Pengaturan Chat</a>.</p>
                    </div>
                </div>
            </div>

            {{-- Device health ranking --}}
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">Rekomendasi Rotasi Device (Anti-Ban)</h5>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover align-middle mb-0" style="min-width: 420px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 140px;">Device</th>
                                        <th style="min-width: 100px;">Skor</th>
                                        <th style="min-width: 90px;">Beban Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody id="wa-rep-devices-body">
                                    <tr><td colspan="3" class="text-center text-muted py-4">Memuat data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const summaryUrl = {{ \Illuminate\Support\Js::from(route('chat.reports.summary')) }};
        const rankingUrl = {{ \Illuminate\Support\Js::from(route('chat.connect-device.health-ranking')) }};

        const fromInput = document.getElementById('wa-report-from');
        const toInput = document.getElementById('wa-report-to');
        const applyBtn = document.getElementById('wa-report-apply');

        function fetchJson(url) {
            return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (res) { return res.json(); });
        }

        function fmtMinutes(value) {
            if (value === null || value === undefined) return '-';
            if (value < 60) return Math.round(value) + ' mnt';
            return (value / 60).toFixed(1) + ' jam';
        }

        function fmtPercent(value) {
            return (value === null || value === undefined) ? '-' : value + '%';
        }

        function healthBadge(label) {
            switch (label) {
                case 'Sehat': return 'bg-success-subtle text-success';
                case 'Perlu Perhatian': return 'bg-warning-subtle text-warning';
                default: return 'bg-danger-subtle text-danger';
            }
        }

        function renderAgents(agents) {
            const body = document.getElementById('wa-rep-agents-body');
            body.innerHTML = '';

            if (!agents || agents.length === 0) {
                body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada data pada periode ini.</td></tr>';
                return;
            }

            agents.forEach(function (agent) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + agent.name + '</td>' +
                    '<td><span class="badge bg-primary-subtle text-primary">' + agent.conversations_handled + '</span></td>' +
                    '<td><span class="badge bg-success-subtle text-success">' + agent.resolved_count + '</span></td>' +
                    '<td>' + fmtMinutes(agent.avg_first_response_minutes) + '</td>' +
                    '<td>' + (agent.breached_count > 0
                        ? '<span class="badge bg-danger-subtle text-danger">' + agent.breached_count + '</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary">0</span>') + '</td>';
                body.appendChild(tr);
            });
        }

        function renderCsat(csat) {
            document.getElementById('wa-rep-csat-score').textContent = csat.avg_score !== null ? (csat.avg_score + ' / 5') : '-';
            document.getElementById('wa-rep-csat-sent').textContent = csat.sent_count;
            document.getElementById('wa-rep-csat-rate').textContent = fmtPercent(csat.response_rate);

            const dist = csat.score_distribution || {};
            const maxCount = Math.max(1, Object.values(dist).reduce(function (a, b) { return Math.max(a, b); }, 0));
            const wrap = document.getElementById('wa-rep-csat-distribution');
            wrap.innerHTML = '';

            for (let score = 5; score >= 1; score--) {
                const count = dist[score] || 0;
                const pct = Math.round((count / maxCount) * 100);

                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 mb-1';
                row.innerHTML =
                    '<span class="fs-12 text-muted" style="width: 42px; flex-shrink:0;">' + score + ' <i class="ri-star-fill text-warning fs-11"></i></span>' +
                    '<div class="progress flex-grow-1" style="height: 8px;"><div class="progress-bar bg-warning" style="width:' + pct + '%"></div></div>' +
                    '<span class="fs-12 text-muted" style="width: 24px; text-align:right; flex-shrink:0;">' + count + '</span>';
                wrap.appendChild(row);
            }
        }

        function renderDevices(devices) {
            const body = document.getElementById('wa-rep-devices-body');
            body.innerHTML = '';

            if (!devices || devices.length === 0) {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Belum ada device.</td></tr>';
                return;
            }

            devices.forEach(function (device) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + (device.phone_number ? ('+' + device.phone_number) : 'Device') + '</td>' +
                    '<td><span class="badge ' + healthBadge(device.label) + '">' + device.score + ' &middot; ' + device.label + '</span></td>' +
                    '<td class="text-muted small">' + device.recent_send_count + 'x / jam terakhir</td>';
                body.appendChild(tr);
            });
        }

        function loadSummary() {
            const params = new URLSearchParams();
            if (fromInput.value) params.set('from', fromInput.value);
            if (toInput.value) params.set('to', toInput.value);

            fetchJson(summaryUrl + '?' + params.toString()).then(function (data) {
                if (!fromInput.value) fromInput.value = data.period.from;
                if (!toInput.value) toInput.value = data.period.to;

                const rr = data.response_resolution;
                document.getElementById('wa-rep-total').textContent = rr.total_conversations;
                document.getElementById('wa-rep-resolved').textContent = rr.resolved_count;
                document.getElementById('wa-rep-avg-first').textContent = fmtMinutes(rr.avg_first_response_minutes);
                document.getElementById('wa-rep-breach-rate').textContent = fmtPercent(rr.first_response_breach_rate);

                renderAgents(data.agents);

                const bc = data.broadcast;
                document.getElementById('wa-rep-bc-total').textContent = bc.total;
                document.getElementById('wa-rep-bc-delivered').textContent = bc.delivered;
                document.getElementById('wa-rep-bc-read').textContent = bc.read;
                document.getElementById('wa-rep-bc-failed').textContent = bc.failed;
                document.getElementById('wa-rep-bc-delivery-rate').textContent = fmtPercent(bc.delivery_rate);
                document.getElementById('wa-rep-bc-read-rate').textContent = fmtPercent(bc.read_rate);
                document.getElementById('wa-rep-bc-delivery-bar').style.width = bc.delivery_rate + '%';
                document.getElementById('wa-rep-bc-read-bar').style.width = bc.read_rate + '%';

                renderCsat(data.csat);
            });
        }

        function loadRanking() {
            fetchJson(rankingUrl).then(function (data) {
                renderDevices(data.devices);
            });
        }

        applyBtn.addEventListener('click', loadSummary);

        loadSummary();
        loadRanking();
    });
</script>
@endsection
