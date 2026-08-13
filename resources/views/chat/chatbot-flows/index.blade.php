@extends('layouts.dashboard')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Chatbot Flow Builder</h4>
                <p class="text-muted mb-0">Percakapan bertahap otomatis — dari kata kunci pemicu sampai aksi seperti tugaskan agent atau tandai selesai.</p>
            </div>
        </div>
    </div>

    {{-- Daftar flow --}}
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                    <h5 class="mb-0">Daftar Flow</h5>
                    <button type="button" class="btn btn-sm btn-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#wa-flow-add-modal">
                        <i class="ri-add-line"></i> Flow Baru
                    </button>
                </div>
                <div id="wa-flow-list" class="list-group list-group-flush">
                    <div class="text-center text-muted py-4">Memuat...</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail flow + steps --}}
    <div class="col-12 col-xl-8">
        <div id="wa-flow-empty-state" class="card h-100">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted py-5">
                <i class="ri-git-branch-line fs-1 mb-2"></i>
                <p class="mb-0">Pilih flow di sebelah kiri, atau buat flow baru untuk mulai menyusun langkah-langkahnya.</p>
            </div>
        </div>

        <div id="wa-flow-detail" class="card h-100 d-none">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1" id="wa-flow-detail-name">-</h5>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-dark-subtle text-dark" id="wa-flow-detail-trigger"></span>
                            <span class="badge" id="wa-flow-detail-status"></span>
                        </div>
                    </div>
                    <div class="d-flex flex-nowrap gap-1">
                        <button type="button" class="btn btn-sm btn-light" id="wa-flow-edit-btn" title="Edit Flow"><i class="ri-edit-line"></i></button>
                        <button type="button" class="btn btn-sm btn-light text-danger" id="wa-flow-delete-btn" title="Hapus Flow"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">Langkah-langkah</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#wa-step-modal" id="wa-step-add-btn">
                        <i class="ri-add-line"></i> Tambah Step
                    </button>
                </div>
                <div id="wa-step-list" class="d-flex flex-column gap-2"></div>
                <p class="text-muted fs-12 mt-2 mb-0" id="wa-step-empty" style="display:none;">Belum ada step. Tambahkan step pertama sebagai titik mulai (Start) flow ini.</p>
            </div>
        </div>
    </div>
</div>

{{-- Modal: tambah/edit flow --}}
<div class="modal fade" id="wa-flow-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wa-flow-modal-title">Flow Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="wa-flow-form-id">
                <div class="mb-3">
                    <label class="form-label">Nama Flow</label>
                    <input type="text" id="wa-flow-form-name" class="form-control" placeholder="Contoh: Booking Konsultasi">
                </div>
                <div class="mb-3">
                    <label class="form-label">Kata Kunci Pemicu</label>
                    <input type="text" id="wa-flow-form-trigger" class="form-control" placeholder="Contoh: booking">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Cara Cocok</label>
                        <select id="wa-flow-form-match-type" class="form-select">
                            <option value="exact">Sama Persis</option>
                            <option value="contains">Mengandung</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Status</label>
                        <select id="wa-flow-form-status" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Timeout Sesi (menit)</label>
                    <input type="number" min="1" id="wa-flow-form-timeout" class="form-control" placeholder="Default: 30">
                </div>
                <div class="text-danger fs-12 mt-2" id="wa-flow-form-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="wa-flow-form-save">Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: tambah/edit step --}}
<div class="modal fade" id="wa-step-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wa-step-modal-title">Tambah Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="wa-step-form-id">
                <div class="mb-3">
                    <label class="form-label">Tipe Step</label>
                    <select id="wa-step-form-type" class="form-select">
                        <option value="message">Pesan — kirim &amp; tunggu balasan bebas</option>
                        <option value="choice">Pilihan — kirim &amp; tunggu balasan bernomor</option>
                        <option value="action">Aksi — jalankan otomatis, tanpa menunggu</option>
                        <option value="end">Selesai — akhiri flow</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Pesan (opsional untuk step Aksi)</label>
                    <textarea id="wa-step-form-message" rows="2" class="form-control" placeholder="Isi pesan yang dikirim pada step ini..."></textarea>
                </div>

                <div class="mb-3" id="wa-step-options-wrap" style="display:none;">
                    <label class="form-label">Pilihan Jawaban</label>
                    <div id="wa-step-options-list" class="d-flex flex-column gap-2 mb-2"></div>
                    <button type="button" class="btn btn-sm btn-light" id="wa-step-option-add"><i class="ri-add-line"></i> Tambah Pilihan</button>
                </div>

                <div class="row g-3 mb-3" id="wa-step-action-wrap" style="display:none;">
                    <div class="col-6">
                        <label class="form-label">Aksi</label>
                        <select id="wa-step-form-action" class="form-select">
                            <option value="assign_conversation">Tugaskan Percakapan</option>
                            <option value="set_status_pending">Tandai Menunggu Pelanggan</option>
                            <option value="set_status_resolved">Tandai Selesai</option>
                            <option value="add_label">Tambah Label</option>
                            <option value="handoff_human">Alihkan ke Agent (akhiri bot)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Nilai (ID User / Label, opsional)</label>
                        <input type="text" id="wa-step-form-action-value" class="form-control" placeholder="Kosongkan untuk otomatis">
                    </div>
                </div>

                <div class="mb-3" id="wa-step-next-wrap">
                    <label class="form-label" id="wa-step-next-label">Lanjut Ke Step</label>
                    <select id="wa-step-form-next" class="form-select">
                        <option value="">— Akhiri flow —</option>
                    </select>
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="wa-step-form-start">
                    <label class="form-check-label" for="wa-step-form-start">Jadikan titik mulai (Start) flow ini</label>
                </div>

                <div class="text-danger fs-12 mt-2" id="wa-step-form-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="wa-step-form-save">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deviceId = {{ \Illuminate\Support\Js::from($deviceId) }};
        const listUrl = {{ \Illuminate\Support\Js::from(route('chatbot-flows.list', ['device' => $deviceId])) }};
        const storeUrl = {{ \Illuminate\Support\Js::from(route('chatbot-flows.store', ['device' => $deviceId])) }};
        const showUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.show', ['device' => $deviceId, 'flow' => '__FLOW__'])) }};
        const updateUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.update', ['device' => $deviceId, 'flow' => '__FLOW__'])) }};
        const destroyUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.destroy', ['device' => $deviceId, 'flow' => '__FLOW__'])) }};
        const stepStoreUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.steps.store', ['device' => $deviceId, 'flow' => '__FLOW__'])) }};
        const stepUpdateUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.steps.update', ['device' => $deviceId, 'flow' => '__FLOW__', 'step' => '__STEP__'])) }};
        const stepDestroyUrlTemplate = {{ \Illuminate\Support\Js::from(route('chatbot-flows.steps.destroy', ['device' => $deviceId, 'flow' => '__FLOW__', 'step' => '__STEP__'])) }};
        const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};

        const flowListEl = document.getElementById('wa-flow-list');
        const emptyStateEl = document.getElementById('wa-flow-empty-state');
        const detailEl = document.getElementById('wa-flow-detail');
        const stepListEl = document.getElementById('wa-step-list');
        const stepEmptyEl = document.getElementById('wa-step-empty');

        const STEP_TYPE_META = {
            message: { icon: 'ri-message-2-line', badge: 'bg-primary-subtle text-primary', label: 'Pesan' },
            choice: { icon: 'ri-list-check-2', badge: 'bg-info-subtle text-info', label: 'Pilihan' },
            action: { icon: 'ri-flashlight-line', badge: 'bg-warning-subtle text-warning', label: 'Aksi' },
            end: { icon: 'ri-flag-line', badge: 'bg-secondary-subtle text-secondary', label: 'Selesai' },
        };

        let flows = [];
        let selectedFlowId = null;
        let currentFlowSteps = [];
        let editingStepId = null;

        function fetchJson(url, options) {
            options = options || {};
            // Object.assign({headers: defaults}, options) would silently
            // WIPE OUT these defaults the moment a caller passes its own
            // headers object (e.g. every save call below, which adds
            // X-CSRF-TOKEN) — Object.assign replaces the whole `headers`
            // key rather than merging its contents. That was dropping
            // Content-Type: application/json on every flow/step save,
            // which made Laravel unable to parse the JSON body at all
            // (silently empty $request->all(), so every "required" field
            // failed validation) — exactly why saving looked broken.
            const headers = Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
            return fetch(url, Object.assign({}, options, { headers: headers }))
                .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); });
        }

        function urlFor(template, flowId, stepId) {
            return template.replace('__FLOW__', flowId || '').replace('__STEP__', stepId || '');
        }

        function firstError(errors, fallback) {
            if (!errors) return fallback;
            const firstKey = Object.keys(errors)[0];
            return firstKey ? errors[firstKey][0] : fallback;
        }

        // --- flow list -------------------------------------------------

        function renderFlowList() {
            flowListEl.innerHTML = '';

            if (flows.length === 0) {
                flowListEl.innerHTML = '<div class="text-center text-muted py-4">Belum ada flow. Klik "Flow Baru" untuk membuat yang pertama.</div>';
                return;
            }

            flows.forEach(function (flow) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action' + (flow.id === selectedFlowId ? ' active' : '');
                item.innerHTML =
                    '<div class="d-flex justify-content-between align-items-center gap-2">' +
                        '<div>' +
                            '<div class="fw-semibold">' + escapeHtml(flow.name) + '</div>' +
                            '<div class="fs-12 ' + (flow.id === selectedFlowId ? 'text-white-50' : 'text-muted') + '">' + escapeHtml(flow.trigger_keyword || '-') + ' &middot; ' + flow.steps_count + ' step</div>' +
                        '</div>' +
                        '<span class="badge ' + (flow.status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary') + '">' + (flow.status === 'active' ? 'Aktif' : 'Nonaktif') + '</span>' +
                    '</div>';
                item.addEventListener('click', function () { selectFlow(flow.id); });
                flowListEl.appendChild(item);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text === null || text === undefined ? '' : String(text);
            return div.innerHTML;
        }

        function loadFlows(selectAfter) {
            fetchJson(listUrl).then(function (result) {
                flows = result.body.flows || [];
                renderFlowList();
                if (selectAfter) selectFlow(selectAfter);
                else if (selectedFlowId) selectFlow(selectedFlowId);
            });
        }

        // --- flow detail -------------------------------------------------

        function selectFlow(flowId) {
            selectedFlowId = flowId;
            renderFlowList();

            fetchJson(urlFor(showUrlTemplate, flowId)).then(function (result) {
                if (!result.ok) return;
                const flow = result.body.flow;
                currentFlowSteps = flow.steps || [];

                emptyStateEl.classList.add('d-none');
                detailEl.classList.remove('d-none');

                document.getElementById('wa-flow-detail-name').textContent = flow.name;
                document.getElementById('wa-flow-detail-trigger').innerHTML = '<i class="ri-price-tag-3-line"></i> ' + escapeHtml(flow.trigger_keyword || '-');

                const statusBadge = document.getElementById('wa-flow-detail-status');
                statusBadge.className = 'badge ' + (flow.status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary');
                statusBadge.textContent = flow.status === 'active' ? 'Aktif' : 'Nonaktif';

                renderSteps();
            });
        }

        function renderSteps() {
            stepListEl.innerHTML = '';
            stepEmptyEl.style.display = currentFlowSteps.length === 0 ? 'block' : 'none';

            currentFlowSteps.forEach(function (step) {
                const meta = STEP_TYPE_META[step.step_type] || STEP_TYPE_META.message;

                const row = document.createElement('div');
                row.className = 'border rounded p-2 d-flex align-items-start gap-2';

                const iconWrap = document.createElement('div');
                iconWrap.className = 'avatar-item rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + meta.badge;
                iconWrap.style.width = '36px';
                iconWrap.style.height = '36px';
                iconWrap.innerHTML = '<i class="' + meta.icon + '"></i>';
                row.appendChild(iconWrap);

                const body = document.createElement('div');
                body.className = 'flex-grow-1';
                body.style.minWidth = '0';

                const headerRow = document.createElement('div');
                headerRow.className = 'd-flex flex-wrap align-items-center gap-1 mb-1';
                headerRow.innerHTML =
                    '<span class="badge ' + meta.badge + '">' + meta.label + '</span>' +
                    (step.is_start ? '<span class="badge bg-dark-subtle text-dark"><i class="ri-play-circle-line"></i> Start</span>' : '');
                body.appendChild(headerRow);

                if (step.message) {
                    const msg = document.createElement('div');
                    msg.className = 'text-truncate';
                    msg.style.maxWidth = '100%';
                    msg.textContent = step.message;
                    body.appendChild(msg);
                }

                if (step.step_type === 'choice' && step.options && step.options.length) {
                    const opts = document.createElement('div');
                    opts.className = 'fs-12 text-muted mt-1';
                    opts.textContent = step.options.map(function (o) { return o.label; }).join(' · ');
                    body.appendChild(opts);
                }

                row.appendChild(body);

                const actions = document.createElement('div');
                actions.className = 'd-flex flex-nowrap gap-1 flex-shrink-0';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-sm btn-light';
                editBtn.title = 'Edit Step';
                editBtn.innerHTML = '<i class="ri-edit-line"></i>';
                editBtn.addEventListener('click', function () { openStepModal(step); });
                actions.appendChild(editBtn);

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-sm btn-light text-danger';
                delBtn.title = 'Hapus Step';
                delBtn.innerHTML = '<i class="ri-delete-bin-line"></i>';
                delBtn.addEventListener('click', function () {
                    if (!confirm('Hapus step ini?')) return;
                    fetchJson(urlFor(stepDestroyUrlTemplate, selectedFlowId, step.id), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    }).then(function () { selectFlow(selectedFlowId); });
                });
                actions.appendChild(delBtn);

                row.appendChild(actions);
                stepListEl.appendChild(row);
            });
        }

        // --- flow add/edit modal ----------------------------------------

        const flowModalEl = document.getElementById('wa-flow-add-modal');
        const flowModal = new bootstrap.Modal(flowModalEl);
        const flowFormError = document.getElementById('wa-flow-form-error');

        document.getElementById('wa-flow-form-save').addEventListener('click', function () {
            flowFormError.style.display = 'none';

            const id = document.getElementById('wa-flow-form-id').value;
            const payload = {
                name: document.getElementById('wa-flow-form-name').value.trim(),
                trigger_keyword: document.getElementById('wa-flow-form-trigger').value.trim(),
                trigger_match_type: document.getElementById('wa-flow-form-match-type').value,
                status: document.getElementById('wa-flow-form-status').value,
                session_timeout_minutes: document.getElementById('wa-flow-form-timeout').value || null,
            };

            const url = id ? urlFor(updateUrlTemplate, id) : storeUrl;
            const method = id ? 'PUT' : 'POST';

            fetchJson(url, { method: method, headers: { 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(payload) })
                .then(function (result) {
                    if (!result.ok) {
                        flowFormError.textContent = firstError(result.body.errors, 'Gagal menyimpan flow.');
                        flowFormError.style.display = 'block';
                        return;
                    }
                    flowModal.hide();
                    loadFlows(result.body.flow.id);
                });
        });

        flowModalEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('wa-flow-form-id').value = '';
            document.getElementById('wa-flow-form-name').value = '';
            document.getElementById('wa-flow-form-trigger').value = '';
            document.getElementById('wa-flow-form-match-type').value = 'exact';
            document.getElementById('wa-flow-form-status').value = 'active';
            document.getElementById('wa-flow-form-timeout').value = '';
            document.getElementById('wa-flow-modal-title').textContent = 'Flow Baru';
            flowFormError.style.display = 'none';
        });

        document.getElementById('wa-flow-edit-btn').addEventListener('click', function () {
            const flow = flows.find(function (f) { return f.id === selectedFlowId; });
            if (!flow) return;

            document.getElementById('wa-flow-modal-title').textContent = 'Edit Flow';
            document.getElementById('wa-flow-form-id').value = flow.id;
            document.getElementById('wa-flow-form-name').value = flow.name;
            document.getElementById('wa-flow-form-trigger').value = flow.trigger_keyword || '';
            document.getElementById('wa-flow-form-match-type').value = flow.trigger_match_type || 'exact';
            document.getElementById('wa-flow-form-status').value = flow.status;
            document.getElementById('wa-flow-form-timeout').value = flow.session_timeout_minutes || '';
            flowModal.show();
        });

        document.getElementById('wa-flow-delete-btn').addEventListener('click', function () {
            if (!selectedFlowId) return;
            if (!confirm('Hapus flow ini beserta seluruh step-nya? Sesi pelanggan yang sedang berjalan di flow ini juga akan dihentikan.')) return;

            fetchJson(urlFor(destroyUrlTemplate, selectedFlowId), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken } })
                .then(function () {
                    selectedFlowId = null;
                    detailEl.classList.add('d-none');
                    emptyStateEl.classList.remove('d-none');
                    loadFlows();
                });
        });

        // --- step add/edit modal ----------------------------------------

        const stepModalEl = document.getElementById('wa-step-modal');
        const stepModal = new bootstrap.Modal(stepModalEl);
        const stepFormError = document.getElementById('wa-step-form-error');
        const stepTypeSelect = document.getElementById('wa-step-form-type');
        const optionsWrap = document.getElementById('wa-step-options-wrap');
        const optionsList = document.getElementById('wa-step-options-list');
        const actionWrap = document.getElementById('wa-step-action-wrap');
        const nextWrap = document.getElementById('wa-step-next-wrap');
        const nextSelect = document.getElementById('wa-step-form-next');
        const nextLabel = document.getElementById('wa-step-next-label');

        function populateNextStepOptions(excludeId) {
            nextSelect.innerHTML = '<option value="">— Akhiri flow —</option>';
            currentFlowSteps.forEach(function (step) {
                const opt = document.createElement('option');
                opt.value = step.id;
                opt.textContent = (STEP_TYPE_META[step.step_type] || {}).label + ': ' + (step.message || step.id).slice(0, 40);
                nextSelect.appendChild(opt);
            });
        }

        function addOptionRow(label, nextStepId) {
            const row = document.createElement('div');
            row.className = 'd-flex gap-2 align-items-center wa-step-option-row';

            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'form-control form-control-sm wa-step-option-label';
            labelInput.placeholder = 'Teks pilihan, misal: Ya';
            labelInput.value = label || '';

            const nextSel = document.createElement('select');
            nextSel.className = 'form-select form-select-sm wa-step-option-next';
            nextSel.style.maxWidth = '220px';
            nextSel.innerHTML = nextSelect.innerHTML;
            nextSel.value = nextStepId || '';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-light text-danger flex-shrink-0';
            removeBtn.innerHTML = '<i class="ri-close-line"></i>';
            removeBtn.addEventListener('click', function () { row.remove(); });

            row.appendChild(labelInput);
            row.appendChild(nextSel);
            row.appendChild(removeBtn);
            optionsList.appendChild(row);
        }

        document.getElementById('wa-step-option-add').addEventListener('click', function () { addOptionRow('', ''); });

        function toggleStepTypeFields() {
            const type = stepTypeSelect.value;
            optionsWrap.style.display = type === 'choice' ? 'block' : 'none';
            actionWrap.style.display = type === 'action' ? 'block' : 'none';
            nextWrap.style.display = type === 'end' ? 'none' : 'block';
            nextLabel.textContent = type === 'choice' ? 'Lanjut Ke Step (jika balasan tidak cocok pilihan manapun)' : 'Lanjut Ke Step';
        }

        stepTypeSelect.addEventListener('change', toggleStepTypeFields);

        function openStepModal(step) {
            editingStepId = step ? step.id : null;
            populateNextStepOptions();

            document.getElementById('wa-step-modal-title').textContent = step ? 'Edit Step' : 'Tambah Step';
            document.getElementById('wa-step-form-id').value = step ? step.id : '';
            stepTypeSelect.value = step ? step.step_type : 'message';
            document.getElementById('wa-step-form-message').value = step ? (step.message || '') : '';
            document.getElementById('wa-step-form-action').value = step ? (step.action || 'assign_conversation') : 'assign_conversation';
            document.getElementById('wa-step-form-action-value').value = step ? (step.action_value || '') : '';
            document.getElementById('wa-step-form-start').checked = step ? !!step.is_start : false;
            nextSelect.value = step ? (step.default_next_step_id || '') : '';

            optionsList.innerHTML = '';
            if (step && step.step_type === 'choice' && step.options) {
                step.options.forEach(function (opt) { addOptionRow(opt.label, opt.next_step_id); });
            }
            if (!step) addOptionRow('', '');

            toggleStepTypeFields();
            stepFormError.style.display = 'none';
            stepModal.show();
        }

        document.getElementById('wa-step-add-btn').addEventListener('click', function () { openStepModal(null); });

        document.getElementById('wa-step-form-save').addEventListener('click', function () {
            stepFormError.style.display = 'none';

            const type = stepTypeSelect.value;
            const payload = {
                step_type: type,
                message: document.getElementById('wa-step-form-message').value.trim() || null,
                is_start: document.getElementById('wa-step-form-start').checked,
                default_next_step_id: type === 'end' ? null : (nextSelect.value || null),
            };

            if (type === 'choice') {
                payload.options = Array.from(optionsList.querySelectorAll('.wa-step-option-row')).map(function (row) {
                    return {
                        label: row.querySelector('.wa-step-option-label').value.trim(),
                        next_step_id: row.querySelector('.wa-step-option-next').value || null,
                    };
                }).filter(function (o) { return o.label !== ''; });
            }

            if (type === 'action') {
                payload.action = document.getElementById('wa-step-form-action').value;
                payload.action_value = document.getElementById('wa-step-form-action-value').value.trim() || null;
            }

            const url = editingStepId
                ? urlFor(stepUpdateUrlTemplate, selectedFlowId, editingStepId)
                : urlFor(stepStoreUrlTemplate, selectedFlowId);
            const method = editingStepId ? 'PUT' : 'POST';

            fetchJson(url, { method: method, headers: { 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(payload) })
                .then(function (result) {
                    if (!result.ok) {
                        stepFormError.textContent = firstError(result.body.errors, 'Gagal menyimpan step.');
                        stepFormError.style.display = 'block';
                        return;
                    }
                    stepModal.hide();
                    selectFlow(selectedFlowId);
                });
        });

        loadFlows();
    });
</script>
@endsection
