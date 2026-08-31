@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')

<link href="{{ asset('be') }}/assets/libs/apexcharts/apexcharts.css" rel="stylesheet">

<div class="row">
    <div class="col-12">

        {{-- Header + date range filter. Moved here from Chat > Laporan
             (App\Http\Controllers\Chat\ChatReportController, now removed) —
             see App\Http\Controllers\DashboardController::summary(). --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-0 flex-wrap gap-3">
                    <div>
                        <h4 class="mb-1">Ringkasan Chat</h4>
                        <p class="text-muted mb-0">Respon &amp; penyelesaian, performa agent, broadcast, dan kepuasan pelanggan (CSAT).</p>
                    </div>
                    <form class="d-flex flex-nowrap align-items-center gap-2" onsubmit="return false;">
                        <input type="date" id="dash-report-from" class="form-control form-control-sm flex-shrink-1" style="max-width: 145px;">
                        <span class="text-muted flex-shrink-0">s/d</span>
                        <input type="date" id="dash-report-to" class="form-control form-control-sm flex-shrink-1" style="max-width: 145px;">
                        <button type="button" id="dash-report-apply" class="btn btn-primary btn-sm flex-shrink-0 text-nowrap"><i class="ri-filter-3-line"></i> Terapkan</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="px-4 py-4 bg-light bg-opacity-30 rounded-3 d-flex align-items-center">
                            <div class="flex-grow-1 text-truncate">
                                <p class="mb-2 text-muted text-nowrap fs-13">Total Percakapan</p>
                                <h5 class="mb-0 fw-bold" id="dash-rep-total">-</h5>
                            </div>
                            <div class="avatar-item avatar-lg rounded-2 avatar-title text-primary bg-primary-subtle border-0 shadow-inset-primary flex-shrink-0">
                                <i class="ri-chat-3-line fs-3"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between gap-2 align-items-center px-2 pt-3 mt-auto">
                            <p class="text-muted mb-0 text-nowrap fs-12">Periode</p>
                            <small class="fw-medium text-primary text-nowrap fs-12" id="dash-rep-period">-</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="px-4 py-4 bg-light bg-opacity-30 rounded-3 d-flex align-items-center">
                            <div class="flex-grow-1 text-truncate">
                                <p class="mb-2 text-muted text-nowrap fs-13">Selesai</p>
                                <h5 class="mb-0 fw-bold" id="dash-rep-resolved">-</h5>
                            </div>
                            <div class="avatar-item avatar-lg rounded-2 avatar-title text-success bg-success-subtle border-0 shadow-inset-success flex-shrink-0">
                                <i class="ri-check-double-line fs-3"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between gap-2 align-items-center px-2 pt-3 mt-auto">
                            <p class="text-muted mb-0 text-nowrap fs-12">Tingkat Penyelesaian</p>
                            <small class="fw-medium text-success text-nowrap fs-12" id="dash-rep-resolved-rate">-</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="px-4 py-4 bg-light bg-opacity-30 rounded-3 d-flex align-items-center">
                            <div class="flex-grow-1 text-truncate">
                                <p class="mb-2 text-muted text-nowrap fs-13">Rata&sup2; Respon Pertama</p>
                                <h5 class="mb-0 fw-bold" id="dash-rep-avg-first">-</h5>
                            </div>
                            <div class="avatar-item avatar-lg rounded-2 avatar-title text-info bg-info-subtle border-0 shadow-inset-info flex-shrink-0">
                                <i class="ri-timer-flash-line fs-3"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between gap-2 align-items-center px-2 pt-3 mt-auto">
                            <p class="text-muted mb-0 text-nowrap fs-12">Rata&sup2; Penyelesaian</p>
                            <small class="fw-medium text-info text-nowrap fs-12" id="dash-rep-avg-resolution">-</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="px-4 py-4 bg-light bg-opacity-30 rounded-3 d-flex align-items-center">
                            <div class="flex-grow-1 text-truncate">
                                <p class="mb-2 text-muted text-nowrap fs-13">SLA Terlambat</p>
                                <h5 class="mb-0 fw-bold" id="dash-rep-breach-rate">-</h5>
                            </div>
                            <div class="avatar-item avatar-lg rounded-2 avatar-title text-warning bg-warning-subtle border-0 shadow-inset-warning flex-shrink-0">
                                <i class="ri-alarm-warning-line fs-3"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between gap-2 align-items-center px-2 pt-3 mt-auto">
                            <p class="text-muted mb-0 text-nowrap fs-12">Resolusi Terlambat</p>
                            <small class="fw-medium text-warning text-nowrap fs-12" id="dash-rep-resolution-breach-rate">-</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Agent performance --}}
            <div class="col-xl-7">
                <div class="card card-h-100">
                    <div class="card-header">
                        <h5 class="card-title">Performa Agent</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 text-center p-3">
                                    <p class="text-muted mb-1 fs-12">Agent</p>
                                    <h5 class="mb-0 fw-bold" id="dash-rep-agent-count">-</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 text-center p-3">
                                    <p class="text-muted mb-1 fs-12">Ditangani</p>
                                    <h5 class="mb-0 fw-bold" id="dash-rep-agent-handled">-</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 text-center p-3">
                                    <p class="text-muted mb-1 fs-12">Selesai</p>
                                    <h5 class="mb-0 fw-bold" id="dash-rep-agent-resolved">-</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 text-center p-3">
                                    <p class="text-muted mb-1 fs-12">Terlambat</p>
                                    <h5 class="mb-0 fw-bold" id="dash-rep-agent-breached">-</h5>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless text-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Agent</th>
                                        <th>Ditangani</th>
                                        <th>Selesai</th>
                                        <th>Rata Respon</th>
                                        <th>Terlambat</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-rep-agents-body">
                                    <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Broadcast --}}
            <div class="col-xl-5">
                <div class="card card-h-100">
                    <div class="card-header">
                        <h5 class="card-title">Broadcast (Pesan Terjadwal)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-6">
                                <div id="dash-rep-broadcast-chart" class="min-h-224px max-h-224px"></div>
                            </div>
                            <div class="col-6">
                                <h3 class="mb-1"><span id="dash-rep-bc-total">-</span> <span class="text-muted fw-normal fs-14">terjadwal</span></h3>
                                <ul class="list-unstyled list-group list-group-sm list-borderless mb-0">
                                    <li class="list-group-item py-1 d-flex justify-content-between">
                                        <span><span class="bullet bg-success me-2"></span>Delivered</span>
                                        <span class="fw-medium" id="dash-rep-bc-delivered">-</span>
                                    </li>
                                    <li class="list-group-item py-1 d-flex justify-content-between">
                                        <span><span class="bullet bg-info me-2"></span>Read</span>
                                        <span class="fw-medium" id="dash-rep-bc-read">-</span>
                                    </li>
                                    <li class="list-group-item py-1 d-flex justify-content-between">
                                        <span><span class="bullet bg-danger me-2"></span>Gagal</span>
                                        <span class="fw-medium" id="dash-rep-bc-failed">-</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-0">
            {{-- CSAT --}}
            <div class="col-xl-6">
                <div class="card card-h-100">
                    <div class="card-header">
                        <h5 class="card-title">Kepuasan Pelanggan (CSAT)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <div id="dash-rep-csat-chart" class="min-h-224px max-h-224px"></div>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex align-items-center gap-4 mb-3 flex-wrap">
                                    <div>
                                        <div class="text-muted small">Rata&sup2; Skor</div>
                                        <h3 class="mb-0" id="dash-rep-csat-score">-</h3>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Terkirim</div>
                                        <h5 class="mb-0" id="dash-rep-csat-sent">-</h5>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Response Rate</div>
                                        <h5 class="mb-0" id="dash-rep-csat-rate">-</h5>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap" id="dash-rep-csat-legend"></div>
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-3 mb-0">Aktifkan survei CSAT otomatis di <a href="{{ route('chat.settings.edit') }}">Pengaturan Chat</a>.</p>
                    </div>
                </div>
            </div>

            {{-- Device health ranking --}}
            <div class="col-xl-6">
                <div class="card card-h-100">
                    <div class="card-header">
                        <h5 class="card-title">Rekomendasi Rotasi Device (Anti-Ban)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless text-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Device</th>
                                        <th>Skor</th>
                                        <th>Beban Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-rep-devices-body">
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

<script src="{{ asset('be') }}/assets/libs/apexcharts/apexcharts.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const summaryUrl = {{ \Illuminate\Support\Js::from(route('dashboard.summary')) }};
        const rankingUrl = {{ \Illuminate\Support\Js::from(route('chat.connect-device.health-ranking')) }};

        const fromInput = document.getElementById('dash-report-from');
        const toInput = document.getElementById('dash-report-to');
        const applyBtn = document.getElementById('dash-report-apply');

        function fetchJson(url) {
            return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (res) {
                if (!res.ok) { throw new Error('request failed: ' + res.status); }
                return res.json();
            });
        }

        function fmtMinutes(value) {
            if (value === null || value === undefined) return '-';
            if (value < 60) return Math.round(value) + ' mnt';
            return (value / 60).toFixed(1) + ' jam';
        }

        function fmtPercent(value) {
            return (value === null || value === undefined) ? '-' : value + '%';
        }

        function fmtDateShort(iso) {
            // 'yyyy-mm-dd' -> 'dd/mm/yy', short enough to stay on one
            // line inside the "Total Percakapan" stat card footer.
            var parts = iso.split('-');
            return parts[2] + '/' + parts[1] + '/' + parts[0].slice(2);
        }

        function initials(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map(function (part) {
                return part.charAt(0).toUpperCase();
            }).join('');
        }

        function healthBadge(label) {
            switch (label) {
                case 'Sehat': return 'bg-success-subtle text-success';
                case 'Perlu Perhatian': return 'bg-warning-subtle text-warning';
                default: return 'bg-danger-subtle text-danger';
            }
        }

        var broadcastChart = null;
        var csatChart = null;

        function renderBroadcastChart(bc) {
            const options = {
                // width disamakan dengan height (persegi) supaya ukuran
                // teks 'Read Rate' & persentase di tengah donut selalu
                // konsisten, tidak ikut membesar mengikuti lebar kolom
                // .col-6 yang bisa berubah-ubah tergantung breakpoint.
                chart: { type: 'radialBar', height: 224, width: 224, toolbar: { show: false } },
                series: [bc.delivery_rate || 0, bc.read_rate || 0],
                labels: ['Delivery', 'Read'],
                colors: ['#28a745', '#17a2b8'],
                plotOptions: {
                    radialBar: {
                        hollow: { size: '35%' },
                        dataLabels: {
                            name: { fontSize: '9px' },
                            value: { fontSize: '11px', formatter: function (val) { return val + '%'; } },
                            total: {
                                show: true,
                                label: 'Read Rate',
                                fontSize: '9px',
                                fontWeight: 500,
                                color: '#6c757d',
                                formatter: function () { return (bc.read_rate || 0) + '%'; }
                            }
                        }
                    }
                },
                legend: { show: false }
            };

            broadcastChart ? broadcastChart.destroy() : null;
            broadcastChart = new ApexCharts(document.querySelector('#dash-rep-broadcast-chart'), options);
            broadcastChart.render();
        }

        function renderCsatChart(csat) {
            const dist = csat.score_distribution || {};
            const series = [5, 4, 3, 2, 1].map(function (score) { return dist[score] || 0; });
            const hasData = series.some(function (v) { return v > 0; });

            const options = {
                chart: { type: 'donut', height: 224, toolbar: { show: false } },
                series: hasData ? series : [1, 1, 1, 1, 1],
                labels: ['Skor 5', 'Skor 4', 'Skor 3', 'Skor 2', 'Skor 1'],
                colors: ['#28a745', '#7fd858', '#6c757d', '#ffc107', '#dc3545'],
                dataLabels: { enabled: false },
                legend: { show: false },
                stroke: { width: 1 },
                plotOptions: { pie: { donut: { size: '55%' } } },
                tooltip: { y: { formatter: function (val) { return hasData ? val + ' survei' : '0 survei'; } } }
            };

            csatChart ? csatChart.destroy() : null;
            csatChart = new ApexCharts(document.querySelector('#dash-rep-csat-chart'), options);
            csatChart.render();

            const colors = ['#28a745', '#7fd858', '#6c757d', '#ffc107', '#dc3545'];
            const legend = document.getElementById('dash-rep-csat-legend');
            legend.innerHTML = '';
            [5, 4, 3, 2, 1].forEach(function (score, idx) {
                const a = document.createElement('div');
                a.className = 'w-50 text-body mb-2 px-2';
                a.innerHTML = '<i class="uis uis-star me-1" style="color:' + colors[idx] + '"></i><span>Skor ' + score + ' (' + (dist[score] || 0) + ')</span>';
                legend.appendChild(a);
            });
        }

        function renderAgents(agents) {
            const body = document.getElementById('dash-rep-agents-body');
            body.innerHTML = '';

            let handled = 0, resolved = 0, breached = 0;

            if (!agents || agents.length === 0) {
                body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada data pada periode ini.</td></tr>';
            } else {
                agents.forEach(function (agent) {
                    handled += agent.conversations_handled;
                    resolved += agent.resolved_count;
                    breached += agent.breached_count;

                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><div class="d-flex align-items-center">' +
                            '<span class="avatar avatar-md rounded-circle me-2 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-semibold">' + initials(agent.name) + '</span>' +
                            '<h6 class="mb-0 fw-semibold">' + agent.name + '</h6>' +
                        '</div></td>' +
                        '<td><span class="badge bg-primary-subtle text-primary">' + agent.conversations_handled + '</span></td>' +
                        '<td><span class="badge bg-success-subtle text-success">' + agent.resolved_count + '</span></td>' +
                        '<td>' + fmtMinutes(agent.avg_first_response_minutes) + '</td>' +
                        '<td>' + (agent.breached_count > 0
                            ? '<span class="badge bg-danger-subtle text-danger">' + agent.breached_count + '</span>'
                            : '<span class="badge bg-secondary-subtle text-secondary">0</span>') + '</td>';
                    body.appendChild(tr);
                });
            }

            document.getElementById('dash-rep-agent-count').textContent = agents ? agents.length : 0;
            document.getElementById('dash-rep-agent-handled').textContent = handled;
            document.getElementById('dash-rep-agent-resolved').textContent = resolved;
            document.getElementById('dash-rep-agent-breached').textContent = breached;
        }

        function renderDevices(devices) {
            const body = document.getElementById('dash-rep-devices-body');
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
                document.getElementById('dash-rep-period').textContent = fmtDateShort(data.period.from) + ' - ' + fmtDateShort(data.period.to);

                const rr = data.response_resolution;
                document.getElementById('dash-rep-total').textContent = rr.total_conversations;
                document.getElementById('dash-rep-resolved').textContent = rr.resolved_count;
                document.getElementById('dash-rep-resolved-rate').textContent = fmtPercent(rr.total_conversations > 0 ? Math.round((rr.resolved_count / rr.total_conversations) * 1000) / 10 : 0);
                document.getElementById('dash-rep-avg-first').textContent = fmtMinutes(rr.avg_first_response_minutes);
                document.getElementById('dash-rep-avg-resolution').textContent = fmtMinutes(rr.avg_resolution_minutes);
                document.getElementById('dash-rep-breach-rate').textContent = fmtPercent(rr.first_response_breach_rate);
                document.getElementById('dash-rep-resolution-breach-rate').textContent = fmtPercent(rr.resolution_breach_rate);

                renderAgents(data.agents);

                const bc = data.broadcast;
                document.getElementById('dash-rep-bc-total').textContent = bc.total;
                document.getElementById('dash-rep-bc-delivered').textContent = bc.delivered;
                document.getElementById('dash-rep-bc-read').textContent = bc.read;
                document.getElementById('dash-rep-bc-failed').textContent = bc.failed;
                renderBroadcastChart(bc);

                document.getElementById('dash-rep-csat-score').textContent = data.csat.avg_score !== null ? (data.csat.avg_score + ' / 5') : '-';
                document.getElementById('dash-rep-csat-sent').textContent = data.csat.sent_count;
                document.getElementById('dash-rep-csat-rate').textContent = fmtPercent(data.csat.response_rate);
                renderCsatChart(data.csat);
            }).catch(function () {
                document.getElementById('dash-rep-agents-body').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat data laporan.</td></tr>';
            });
        }

        function loadRanking() {
            // Device health ranking still lives under Chat > Connect Device
            // (its own 'active.package' + 'menu.access' gate) — a role
            // without access to that feature simply sees an empty state
            // here instead of breaking the rest of the dashboard.
            fetchJson(rankingUrl).then(function (data) {
                renderDevices(data.devices);
            }).catch(function () {
                document.getElementById('dash-rep-devices-body').innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Data device tidak tersedia.</td></tr>';
            });
        }

        applyBtn.addEventListener('click', loadSummary);

        loadSummary();
        loadRanking();
    });
</script>

@endsection
