{{--
    Shared by create.blade.php / edit.blade.php. $schedule is null on
    create. $templates / $branchOffices / $companyMembers come from
    MessageScheduleController::formData(). One form now covers all 3
    WaMessageSchedule types (Jenis Pengiriman selector below) — see that
    controller's docblock.
--}}
@php
    $existingRecipients = collect($schedule->recipients ?? []);
    $existingPhones = $existingRecipients->where('type', 'phone')->pluck('value')->implode("\n");
    $existingGroups = $existingRecipients->where('type', 'group')->pluck('value')->all();
    $existingUsers = $existingRecipients->where('type', 'user')->pluck('value')->all();
    $currentType = old('type', $schedule->type ?? 'recurring');

    // Pre-built here as plain PHP so the JS block below only ever has
    // to embed a single already-computed variable — keeps every raw
    // echo in this file to a simple one-liner instead of a multi-line
    // expression.
    $templatesForJs = $templates->map(function ($t) {
        return ['id' => $t->id, 'name' => $t->name, 'template' => $t->template];
    })->values();
@endphp

<div class="mb-3">
    <label class="form-label">Jenis Pengiriman</label>
    <select name="type" id="scheduleTypeSelect" class="form-select @error('type') is-invalid @enderror">
        <option value="once" @selected($currentType == 'once')>Sekali Kirim (broadcast langsung/terjadwal 1x)</option>
        <option value="recurring" @selected($currentType == 'recurring')>Berulang Setiap Hari (rentang tanggal)</option>
        <option value="drip" @selected($currentType == 'drip')>Bertahap per Kontak (drip, beberapa pesan berjarak hari)</option>
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Device</label>
        <select name="device_id" id="scheduleDeviceSelect" class="wa-device-select form-select @error('device_id') is-invalid @enderror"
            data-selected="{{ old('device_id', $schedule->device_id ?? '') }}" required>
            <option value="">Memuat device...</option>
        </select>
        @error('device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Nama / Judul</label>
        <input type="text" name="title" value="{{ old('title', $schedule->title ?? '') }}"
            class="form-control @error('title') is-invalid @enderror" placeholder="Contoh: Reminder Tagihan Bulanan" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- ============================================================
     Isi Pesan: template WA vs kategori+isi manual — hanya untuk
     jenis 'once'/'recurring'. Jenis 'drip' pakai section Langkah
     Pesan di bawah, tiap langkah punya isinya sendiri.
============================================================ --}}
<div class="card border bg-light-subtle mb-3" id="contentSection">
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="useTemplateToggle" name="use_template" value="1"
                @checked(old('use_template', $schedule->use_template ?? false))>
            <label class="form-check-label fw-semibold" for="useTemplateToggle">
                <i class="ri-file-list-3-line"></i> Gunakan Template WA
            </label>
            <div class="form-text mb-0">Aktifkan untuk memilih dari <a href="{{ route('chat.message-templates.index') }}" target="_blank">WA Template</a> yang sudah dibuat, atau matikan untuk menulis pesan manual.</div>
        </div>

        <div id="templateFields" style="display:none;">
            <label class="form-label">Pilih Template</label>
            <select name="wa_message_template_id" class="form-select @error('wa_message_template_id') is-invalid @enderror">
                <option value="">-- Pilih Template --</option>
                @forelse($templates as $tpl)
                    <option value="{{ $tpl->id }}" data-preview="{{ $tpl->template }}"
                        @selected(old('wa_message_template_id', $schedule->wa_message_template_id ?? '') == $tpl->id)>
                        {{ $tpl->name }}
                    </option>
                @empty
                    <option value="" disabled>Belum ada template aktif</option>
                @endforelse
            </select>
            @error('wa_message_template_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="border rounded-3 p-2 mt-2 bg-white small text-muted" id="templatePreview" style="min-height:44px;">
                Pilih template untuk melihat isi pesannya di sini.
            </div>
        </div>

        <div id="manualFields">
            <div class="mb-3">
                <label class="form-label">Kategori</label>
                <select name="category_schedule" class="form-select @error('category_schedule') is-invalid @enderror">
                    <option value="text" @selected(old('category_schedule', $schedule->category_schedule ?? 'text') == 'text')>Text</option>
                    <option value="location" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'location')>Location</option>
                    <option value="image" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'image')>Image</option>
                    <option value="document" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'document')>Document</option>
                </select>
                @error('category_schedule')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-0">
                <label class="form-label">Isi Pesan</label>
                <textarea name="message" rows="4" class="form-control @error('message') is-invalid @enderror"
                    placeholder="Tulis isi pesan...">{{ old('message', $schedule->message ?? '') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     Jadwal: tanggal + jam. Label & kolom "Tanggal Berakhir"
     menyesuaikan Jenis Pengiriman lewat JS di bawah.
============================================================ --}}
<div class="row" id="dateFieldsRow">
    <div class="col-md-4 mb-3">
        <label class="form-label" id="dateStartLabel">Tanggal Mulai</label>
        <input type="date" name="date_start"
            value="{{ old('date_start', isset($schedule->date_start) ? $schedule->date_start->format('Y-m-d') : '') }}"
            class="form-control @error('date_start') is-invalid @enderror" required>
        @error('date_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3" id="dateEndCol">
        <label class="form-label">Tanggal Berakhir</label>
        <input type="date" name="date_end"
            value="{{ old('date_end', isset($schedule->date_end) ? $schedule->date_end->format('Y-m-d') : '') }}"
            class="form-control @error('date_end') is-invalid @enderror">
        <div class="form-text">Kosongkan kalau pesan cuma dikirim 1 hari. Isi untuk kirim berulang setiap hari sampai tanggal ini.</div>
        @error('date_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" id="scheduleTimeLabel">Jam Kirim</label>
        <input type="time" name="schedule_time" value="{{ old('schedule_time', isset($schedule->schedule_time) ? \Illuminate\Support\Carbon::parse($schedule->schedule_time)->format('H:i') : '') }}"
            class="form-control @error('schedule_time') is-invalid @enderror" required>
        @error('schedule_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- ============================================================
     Langkah Pesan (khusus jenis 'drip') — tiap langkah punya jarak
     hari + isi pesan sendiri (manual atau template).
============================================================ --}}
<div class="card border bg-light-subtle mb-3" id="dripStepsSection" style="display:none;">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label fw-semibold mb-0"><i class="ri-flow-chart"></i> Langkah Pesan (Drip)</label>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addStepBtn"><i class="ri-add-line"></i> Tambah Langkah</button>
        </div>
        <div class="form-text mb-2">Tiap langkah terkirim otomatis sekian hari setelah Tanggal Mulai, ke semua tujuan yang dipilih di bawah.</div>
        @error('steps')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div id="stepsContainer"></div>
    </div>
</div>

{{-- ============================================================
     Tujuan Pengiriman: 3 tab (nomor / grup WA / user company) —
     selalu tampil untuk ketiga jenis pengiriman.
============================================================ --}}
<div class="mb-3">
    <label class="form-label d-block">Tujuan Pengiriman</label>
    @error('recipients')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

    <ul class="nav nav-tabs" id="recipientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-phone-btn" data-bs-toggle="tab" data-bs-target="#tab-phone" type="button" role="tab">
                <i class="ri-smartphone-line"></i> Nomor Tujuan
                <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="countPhone">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-group-btn" data-bs-toggle="tab" data-bs-target="#tab-group" type="button" role="tab">
                <i class="ri-group-line"></i> Grup WhatsApp
                <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="countGroup">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-user-btn" data-bs-toggle="tab" data-bs-target="#tab-user" type="button" role="tab">
                <i class="ri-team-line"></i> User Company
                <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="countUser">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom-3 p-3">
        {{-- Tab 1: Nomor WhatsApp --}}
        <div class="tab-pane fade show active" id="tab-phone" role="tabpanel">
            <label class="form-label">Nomor WhatsApp Tujuan</label>
            <textarea name="phone_numbers" id="phoneNumbersInput" rows="4" class="form-control"
                placeholder="6281234567890; 6281298765432&#10;atau satu nomor per baris">{{ old('phone_numbers', $existingPhones) }}</textarea>
            <div class="form-text">Pisahkan tiap nomor dengan titik-koma (;), koma, atau baris baru.</div>
        </div>

        {{-- Tab 2: Grup WhatsApp --}}
        <div class="tab-pane fade" id="tab-group" role="tabpanel">
            <label class="form-label">Pilih Grup WhatsApp</label>
            <div id="groupChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;"
                data-selected='{!! json_encode($existingGroups) !!}'>
                <p class="text-muted small mb-0">Pilih device terlebih dahulu untuk memuat daftar grup.</p>
            </div>
        </div>

        {{-- Tab 3: User Company (Branch -> Unit -> checklist) --}}
        <div class="tab-pane fade" id="tab-user" role="tabpanel">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Branch Office</label>
                    <select id="userBranchFilter" class="form-select form-select-sm">
                        <option value="">-- Semua Branch --</option>
                        @foreach($branchOffices as $branchOffice)
                            <option value="{{ $branchOffice->id }}">{{ $branchOffice->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Unit / Divisi</label>
                    <select id="userUnitFilter" class="form-select form-select-sm">
                        <option value="">-- Semua Unit --</option>
                        @foreach($branchOffices as $branchOffice)
                            @foreach($branchOffice->units as $unit)
                                <option value="{{ $unit->id }}" data-branch-office="{{ $branchOffice->id }}">{{ $unit->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="userSelectAll">
                <label class="form-check-label" for="userSelectAll">Pilih Semua (yang tampil)</label>
            </div>

            <div id="userChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;">
                @forelse($companyMembers as $member)
                    @continue(! $member->user)
                    <div class="form-check user-checklist-item"
                        data-branch-office="{{ $member->branch_office_id ?? '' }}"
                        data-branch-office-unit="{{ $member->branch_office_unit_id ?? '' }}">
                        <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]"
                            id="member_{{ $member->user->id }}" value="{{ $member->user->id }}"
                            @checked(in_array($member->user->id, old('user_ids', $existingUsers)))
                            {{ ! $member->user->handphone ? 'disabled' : '' }}>
                        <label class="form-check-label" for="member_{{ $member->user->id }}">
                            {{ $member->user->name }}
                            <span class="text-muted small">
                                — {{ $member->branchOffice->name ?? 'Tanpa Branch' }}{{ $member->branchOfficeUnit ? ' / '.$member->branchOfficeUnit->name : '' }}
                                {{ ! $member->user->handphone ? '(belum ada no. WA)' : '' }}
                            </span>
                        </label>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada user company. Tambahkan dari Setting Users di halaman Profile.</p>
                @endforelse
            </div>
            @error('user_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="mb-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $schedule->status ?? 'active') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $schedule->status ?? '') == 'inactive')>Inactive</option>
    </select>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

{{-- Data step yang sudah ada (edit) — dibaca oleh JS untuk mengisi
     #stepsContainer saat halaman dimuat. Kosong array di create. --}}
@php
    $existingStepsForJs = collect($schedule->steps ?? [])->map(function ($step) {
        return [
            'delay_days' => $step->delay_days,
            'use_template' => $step->use_template,
            'wa_message_template_id' => $step->wa_message_template_id,
            'category_schedule' => $step->category_schedule ?? 'text',
            'message' => $step->message,
            'status' => $step->status,
        ];
    })->values();
@endphp
<script type="application/json" id="existingStepsData">{!! json_encode($existingStepsForJs) !!}</script>

<script>
(function () {
    // --- Jenis Pengiriman: toggle content section / date fields / steps section ---
    var typeSelect = document.getElementById('scheduleTypeSelect');
    var contentSection = document.getElementById('contentSection');
    var dripStepsSection = document.getElementById('dripStepsSection');
    var dateStartLabel = document.getElementById('dateStartLabel');
    var dateEndCol = document.getElementById('dateEndCol');
    var scheduleTimeLabel = document.getElementById('scheduleTimeLabel');

    function syncTypeSections() {
        var type = typeSelect.value;

        contentSection.style.display = type === 'drip' ? 'none' : '';
        dripStepsSection.style.display = type === 'drip' ? '' : 'none';
        dateEndCol.style.display = type === 'recurring' ? '' : 'none';

        if (type === 'once') {
            dateStartLabel.textContent = 'Tanggal Kirim';
            scheduleTimeLabel.textContent = 'Jam Kirim';
        } else if (type === 'drip') {
            dateStartLabel.textContent = 'Tanggal Mulai (Enrollment)';
            scheduleTimeLabel.textContent = 'Jam Kirim (semua langkah)';
        } else {
            dateStartLabel.textContent = 'Tanggal Mulai';
            scheduleTimeLabel.textContent = 'Jam Kirim';
        }
    }

    typeSelect.addEventListener('change', syncTypeSections);
    syncTypeSections();

    // --- Template vs manual message toggle (jenis once/recurring) ---
    var useTemplateToggle = document.getElementById('useTemplateToggle');
    var templateFields = document.getElementById('templateFields');
    var manualFields = document.getElementById('manualFields');
    var templateSelect = templateFields.querySelector('select[name="wa_message_template_id"]');
    var templatePreview = document.getElementById('templatePreview');

    function syncTemplateToggle() {
        var on = useTemplateToggle.checked;
        templateFields.style.display = on ? '' : 'none';
        manualFields.style.display = on ? 'none' : '';
    }

    function syncTemplatePreview() {
        var opt = templateSelect.options[templateSelect.selectedIndex];
        templatePreview.textContent = (opt && opt.getAttribute('data-preview')) || 'Pilih template untuk melihat isi pesannya di sini.';
    }

    useTemplateToggle.addEventListener('change', syncTemplateToggle);
    templateSelect.addEventListener('change', syncTemplatePreview);
    syncTemplateToggle();
    syncTemplatePreview();

    // --- Langkah Pesan (drip) — repeatable step rows ---
    var stepsContainer = document.getElementById('stepsContainer');
    var addStepBtn = document.getElementById('addStepBtn');
    var templatesOptionsHtml = {!! json_encode($templatesForJs) !!};
    var stepIndex = 0;

    function buildTemplateOptions(selectedId) {
        var html = '<option value="">-- Pilih Template --</option>';
        templatesOptionsHtml.forEach(function (t) {
            html += '<option value="' + t.id + '" data-preview="' + t.template.replace(/"/g, '&quot;') + '"' + (t.id === selectedId ? ' selected' : '') + '>' + t.name + '</option>';
        });
        return html;
    }

    function addStepRow(prefill) {
        prefill = prefill || {};
        var idx = stepIndex++;
        var useTpl = !!prefill.use_template;

        var wrap = document.createElement('div');
        wrap.className = 'border rounded-3 p-3 mb-2 bg-white step-row';
        wrap.innerHTML =
            '<div class="d-flex justify-content-between align-items-start mb-2">' +
                '<span class="badge bg-secondary-subtle text-secondary step-number">Langkah</span>' +
                '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-step-btn"><i class="ri-close-line"></i> Hapus</button>' +
            '</div>' +
            '<div class="row">' +
                '<div class="col-md-3 mb-2">' +
                    '<label class="form-label small">Kirim H+ (hari setelah mulai)</label>' +
                    '<input type="number" min="0" class="form-control form-control-sm" name="steps[' + idx + '][delay_days]" value="' + (prefill.delay_days ?? 0) + '">' +
                '</div>' +
                '<div class="col-md-3 mb-2">' +
                    '<label class="form-label small">Status</label>' +
                    '<select class="form-select form-select-sm" name="steps[' + idx + '][status]">' +
                        '<option value="active"' + (prefill.status !== 'inactive' ? ' selected' : '') + '>Active</option>' +
                        '<option value="inactive"' + (prefill.status === 'inactive' ? ' selected' : '') + '>Inactive</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-6 mb-2 d-flex align-items-end">' +
                    '<div class="form-check form-switch">' +
                        '<input class="form-check-input step-use-template" type="checkbox" role="switch" name="steps[' + idx + '][use_template]" value="1"' + (useTpl ? ' checked' : '') + '>' +
                        '<label class="form-check-label">Gunakan Template WA</label>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="step-template-fields" style="display:' + (useTpl ? '' : 'none') + ';">' +
                '<select class="form-select form-select-sm mb-2" name="steps[' + idx + '][wa_message_template_id]">' +
                    buildTemplateOptions(prefill.wa_message_template_id || '') +
                '</select>' +
            '</div>' +
            '<div class="step-manual-fields" style="display:' + (useTpl ? 'none' : '') + ';">' +
                '<div class="row">' +
                    '<div class="col-md-4 mb-2">' +
                        '<select class="form-select form-select-sm" name="steps[' + idx + '][category_schedule]">' +
                            '<option value="text"' + ((prefill.category_schedule || 'text') === 'text' ? ' selected' : '') + '>Text</option>' +
                            '<option value="location"' + (prefill.category_schedule === 'location' ? ' selected' : '') + '>Location</option>' +
                            '<option value="image"' + (prefill.category_schedule === 'image' ? ' selected' : '') + '>Image</option>' +
                            '<option value="document"' + (prefill.category_schedule === 'document' ? ' selected' : '') + '>Document</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="col-md-8 mb-2">' +
                        '<textarea class="form-control form-control-sm" rows="2" placeholder="Isi pesan langkah ini..." name="steps[' + idx + '][message]">' + (prefill.message || '') + '</textarea>' +
                    '</div>' +
                '</div>' +
            '</div>';

        stepsContainer.appendChild(wrap);
        renumberSteps();

        wrap.querySelector('.remove-step-btn').addEventListener('click', function () {
            wrap.remove();
            renumberSteps();
        });

        var stepToggle = wrap.querySelector('.step-use-template');
        var tplFields = wrap.querySelector('.step-template-fields');
        var manFields = wrap.querySelector('.step-manual-fields');
        stepToggle.addEventListener('change', function () {
            tplFields.style.display = stepToggle.checked ? '' : 'none';
            manFields.style.display = stepToggle.checked ? 'none' : '';
        });
    }

    function renumberSteps() {
        var rows = stepsContainer.querySelectorAll('.step-row');
        rows.forEach(function (row, i) {
            var badge = row.querySelector('.step-number');
            if (badge) badge.textContent = 'Langkah ' + (i + 1);
        });
    }

    addStepBtn.addEventListener('click', function () { addStepRow(); });

    var existingSteps = [];
    try { existingSteps = JSON.parse(document.getElementById('existingStepsData').textContent || '[]'); } catch (e) {}
    if (existingSteps.length) {
        existingSteps.forEach(function (s) { addStepRow(s); });
    } else if (typeSelect.value === 'drip') {
        addStepRow();
    }

    // --- Tab badge counters ---
    var phoneInput = document.getElementById('phoneNumbersInput');
    var countPhone = document.getElementById('countPhone');
    var countGroup = document.getElementById('countGroup');
    var countUser = document.getElementById('countUser');

    function updatePhoneCount() {
        var items = phoneInput.value.split(/[;,\r\n]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        countPhone.textContent = items.length;
    }
    function updateGroupCount() {
        countGroup.textContent = document.querySelectorAll('#groupChecklist input[type=checkbox]:checked').length;
    }
    function updateUserCount() {
        countUser.textContent = document.querySelectorAll('#userChecklist input.user-checkbox:checked').length;
    }

    phoneInput.addEventListener('input', updatePhoneCount);
    updatePhoneCount();
    updateUserCount();

    // --- Tab 2: Grup WhatsApp, loaded per selected device ---
    var deviceSelect = document.getElementById('scheduleDeviceSelect');
    var groupChecklist = document.getElementById('groupChecklist');
    var chatsUrlTemplate = {!! json_encode(route('inbox.chats', ['device' => '__DEVICEID__'])) !!};
    // Built via concatenation rather than one literal string — the
    // at-sign immediately followed by a letter anywhere in a Blade
    // template (even inside a JS string) gets scanned as a possible
    // directive, so it's split here to avoid that pattern entirely.
    var GROUP_JID_SUFFIX = '@' + 'g.us';

    function loadGroupsFor(deviceId) {
        if (!deviceId) {
            groupChecklist.innerHTML = '<p class="text-muted small mb-0">Pilih device terlebih dahulu untuk memuat daftar grup.</p>';
            updateGroupCount();
            return;
        }
        groupChecklist.innerHTML = '<p class="text-muted small mb-0">Memuat grup WhatsApp...</p>';

        var preSelected = [];
        try { preSelected = JSON.parse(groupChecklist.getAttribute('data-selected') || '[]'); } catch (e) {}

        fetch(chatsUrlTemplate.replace('__DEVICEID__', deviceId), { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var groups = (data.chats || []).filter(function (c) {
                    return typeof c.chat_jid === 'string' && c.chat_jid.indexOf(GROUP_JID_SUFFIX) !== -1;
                });

                if (groups.length === 0) {
                    groupChecklist.innerHTML = '<p class="text-muted small mb-0">Tidak ada grup WhatsApp pada device ini.</p>';
                    updateGroupCount();
                    return;
                }

                groupChecklist.innerHTML = '';
                groups.forEach(function (group, idx) {
                    var checked = preSelected.indexOf(group.chat_jid) !== -1;
                    var inputId = 'group_' + idx;

                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';

                    var input = document.createElement('input');
                    input.className = 'form-check-input';
                    input.type = 'checkbox';
                    input.name = 'group_jids[]';
                    input.value = group.chat_jid;
                    input.id = inputId;
                    input.checked = checked;
                    input.addEventListener('change', updateGroupCount);

                    var label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.setAttribute('for', inputId);
                    label.textContent = group.name || group.chat_jid;

                    wrap.appendChild(input);
                    wrap.appendChild(label);
                    groupChecklist.appendChild(wrap);
                });
                updateGroupCount();
            })
            .catch(function () {
                groupChecklist.innerHTML = '<p class="text-danger small mb-0">Gagal memuat grup WhatsApp.</p>';
                updateGroupCount();
            });
    }

    deviceSelect.addEventListener('change', function () { loadGroupsFor(deviceSelect.value); });
    var initialDeviceId = deviceSelect.getAttribute('data-selected');
    if (initialDeviceId) loadGroupsFor(initialDeviceId);

    // --- Tab 3: Company Users — branch -> unit filter + select all ---
    var branchFilter = document.getElementById('userBranchFilter');
    var unitFilter = document.getElementById('userUnitFilter');
    var selectAll = document.getElementById('userSelectAll');
    var allUnitOptions = Array.prototype.slice.call(unitFilter.querySelectorAll('option[data-branch-office]'));
    var userItems = Array.prototype.slice.call(document.querySelectorAll('.user-checklist-item'));

    function filterUnitsByBranch() {
        var branchId = branchFilter.value;
        allUnitOptions.forEach(function (opt) {
            var matches = !branchId || opt.getAttribute('data-branch-office') === branchId;
            opt.hidden = !matches;
            opt.disabled = !matches;
        });
        var selected = unitFilter.querySelector('option:checked');
        if (selected && selected.hasAttribute('data-branch-office') && selected.getAttribute('data-branch-office') !== branchId) {
            unitFilter.value = '';
        }
    }

    function applyUserFilter() {
        var branchId = branchFilter.value;
        var unitId = unitFilter.value;

        userItems.forEach(function (item) {
            var matchesBranch = !branchId || item.getAttribute('data-branch-office') === branchId;
            var matchesUnit = !unitId || item.getAttribute('data-branch-office-unit') === unitId;
            item.style.display = (matchesBranch && matchesUnit) ? '' : 'none';
        });

        selectAll.checked = false;
    }

    branchFilter.addEventListener('change', function () { filterUnitsByBranch(); applyUserFilter(); });
    unitFilter.addEventListener('change', applyUserFilter);

    selectAll.addEventListener('change', function () {
        userItems.forEach(function (item) {
            if (item.style.display === 'none') return;
            var checkbox = item.querySelector('.user-checkbox');
            if (checkbox && !checkbox.disabled) checkbox.checked = selectAll.checked;
        });
        updateUserCount();
    });

    document.querySelectorAll('.user-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateUserCount);
    });
})();
</script>
