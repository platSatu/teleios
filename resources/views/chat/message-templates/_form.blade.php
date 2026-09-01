@php
    $btn = old('buttons', $template->buttons ?? []);
    $varsExample = old('variables_example', $template->variables_example ?? []);
    $currentContentType = old('content_type', $template->content_type ?? 'text');

    $existingRecipients = collect($template->recipients ?? []);
    $existingPhones = $existingRecipients->where('type', 'phone')->pluck('value')->implode("\n");
    $existingGroups = $existingRecipients->where('type', 'group')->pluck('value')->all();
    $existingUsers = $existingRecipients->where('type', 'user')->pluck('value')->all();
@endphp

{{-- ============================================================
     Card 1 — Informasi dasar: nama, kategori, jenis konten.
============================================================ --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Informasi Template</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Nama Template <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
                class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Promo Akhir Bulan" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <select name="wa_category_template_id" class="form-select @error('wa_category_template_id') is-invalid @enderror">
                <option value="">Tanpa kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('wa_category_template_id', $template->wa_category_template_id ?? '') == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Hanya kategori yang sudah lolos moderasi AI yang muncul di sini.</div>
            @error('wa_category_template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- ============================================================
             Jenis Konten — hardcoded 3 tingkat, sama pola dengan "Jenis
             Pengiriman" di form Pesan Terjadwal. Tiap tingkat menambah
             field di atas tingkat sebelumnya: text < text_link <
             text_link_file.
        ============================================================ --}}
        <div class="mb-0">
            <label class="form-label d-block mb-2">Jenis Konten</label>
            <div class="row g-2" id="tplContentTypeCards">
                <div class="col-sm-4">
                    <input type="radio" class="btn-check tpl-content-type-radio" name="content_type" id="tplContentType-text"
                        value="text" autocomplete="off" @checked($currentContentType == 'text')>
                    <label class="btn btn-outline-primary w-100 h-100 text-start p-3 tpl-content-type-card" for="tplContentType-text">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="ri-file-text-line fs-4"></i>
                            <span class="fw-semibold">Teks</span>
                        </div>
                        <div class="small text-body-secondary">Header + isi pesan saja.</div>
                    </label>
                </div>
                <div class="col-sm-4">
                    <input type="radio" class="btn-check tpl-content-type-radio" name="content_type" id="tplContentType-text_link"
                        value="text_link" autocomplete="off" @checked($currentContentType == 'text_link')>
                    <label class="btn btn-outline-primary w-100 h-100 text-start p-3 tpl-content-type-card" for="tplContentType-text_link">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="ri-link fs-4"></i>
                            <span class="fw-semibold">Teks + Link</span>
                        </div>
                        <div class="small text-body-secondary">Tambah 1 baris link (mis. website/lokasi).</div>
                    </label>
                </div>
                <div class="col-sm-4">
                    <input type="radio" class="btn-check tpl-content-type-radio" name="content_type" id="tplContentType-text_link_file"
                        value="text_link_file" autocomplete="off" @checked($currentContentType == 'text_link_file')>
                    <label class="btn btn-outline-primary w-100 h-100 text-start p-3 tpl-content-type-card" for="tplContentType-text_link_file">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="ri-attachment-2 fs-4"></i>
                            <span class="fw-semibold">Lengkap</span>
                        </div>
                        <div class="small text-body-secondary">Teks + link + lampiran file.</div>
                    </label>
                </div>
            </div>
            @error('content_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<style>
    .tpl-content-type-radio:checked + .tpl-content-type-card .text-body-secondary {
        color: rgba(255, 255, 255, .85) !important;
    }
</style>

{{-- ============================================================
     Card 2 — Isi pesan: header, isi pesan, footer, link, lampiran,
     contoh variabel, tombol aksi.
============================================================ --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Isi Pesan</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label d-flex justify-content-between">
                <span>Header <span class="text-muted fw-normal">(opsional)</span></span>
                <small class="text-muted"><span id="tpl-header-count">0</span>/60</small>
            </label>
            <input type="text" name="header" id="tpl-header" maxlength="60"
                value="{{ old('header', $template->header ?? '') }}"
                class="form-control @error('header') is-invalid @enderror" placeholder="Judul singkat di atas pesan">
            @error('header')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-2">
            <label class="form-label d-flex justify-content-between">
                <span>Isi Pesan <span class="text-danger">*</span></span>
                <small class="text-muted"><span id="tpl-body-count">0</span>/1024</small>
            </label>
            <div class="btn-toolbar gap-1 mb-1" role="toolbar">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-wrap="*" title="Bold"><i class="ri-bold"></i> Bold</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-wrap="_" title="Italic"><i class="ri-italic"></i> Italic</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-wrap="~" title="Strikethrough"><i class="ri-strikethrough"></i> Coret</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-wrap="```" title="Monospace"><i class="ri-code-line"></i> Mono</button>
                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="tpl-insert-variable" title="Sisipkan variabel">
                    <i class="ri-braces-line"></i> Variabel
                </button>
                <button type="button" class="btn btn-sm btn-light text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Variabel seperti @{{nama}} akan diganti otomatis dengan data asli tiap kontak saat pesan dikirim (mis. @{{nama}} jadi 'Budi'). Isi contoh nilainya di kolom 'Contoh Nilai Variabel' di bawah supaya preview menampilkan hasil yang realistis.">
                    <i class="ri-information-line"></i>
                </button>
            </div>
            <textarea name="template" id="tpl-body" rows="6" maxlength="1024"
                class="form-control @error('template') is-invalid @enderror"
                placeholder="Halo @{{nama}}, terima kasih sudah bergabung...">{{ old('template', $template->template ?? '') }}</textarea>
            <div class="form-text">
                Gunakan <code>*tebal*</code>, <code>_miring_</code>, <code>~coret~</code>, <code>```mono```</code>, dan <code>@{{variabel}}</code> untuk personalisasi. Tag HTML tidak diperbolehkan.
            </div>
            @error('template')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label d-flex justify-content-between">
                <span>Footer <span class="text-muted fw-normal">(opsional)</span></span>
                <small class="text-muted"><span id="tpl-footer-count">0</span>/60</small>
            </label>
            <input type="text" name="footer" id="tpl-footer" maxlength="60"
                value="{{ old('footer', $template->footer ?? '') }}"
                class="form-control @error('footer') is-invalid @enderror" placeholder="Catatan kecil di bawah pesan">
            @error('footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Muncul untuk content_type = text_link / text_link_file --}}
        <div class="mb-3" id="tpl-link-wrapper" style="display:none;">
            <label class="form-label">Link</label>
            <input type="text" name="link" id="tpl-link" maxlength="2000"
                value="{{ old('link', $template->link ?? '') }}"
                class="form-control @error('link') is-invalid @enderror" placeholder="https://... (website, lokasi, dsb)">
            @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        {{-- Muncul untuk content_type = text_link_file --}}
        <div class="mb-3" id="tpl-attachment-wrapper" style="display:none;">
            <label class="form-label">Lampiran File</label>
            @if ($template && $template->attachment_path)
                <div class="d-flex align-items-center gap-2 border rounded p-2 mb-2 bg-light-subtle" id="tpl-existing-attachment">
                    <i class="ri-file-3-line fs-4"></i>
                    <div class="flex-grow-1 small">
                        <a href="{{ asset('storage/'.$template->attachment_path) }}" target="_blank">{{ $template->attachment_original_name }}</a>
                        <div class="text-muted">{{ number_format(($template->attachment_size ?? 0) / 1024, 0) }} KB</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="tpl-remove-attachment">
                        <label class="form-check-label small text-danger" for="tpl-remove-attachment">Hapus</label>
                    </div>
                </div>
            @endif
            <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror">
            <div class="form-text">
                Video (mp4/3gp/mov maks 16MB), Gambar (jpg/jpeg/png maks 5MB), Dokumen (pdf/doc/docx/xls/xlsx maks 10MB), atau Teks (txt maks 2MB).
            </div>
            @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3" id="tpl-variables-wrapper" style="display:none;">
            <label class="form-label">Contoh Nilai Variabel</label>
            <div id="tpl-variables-list" class="d-flex flex-column gap-2"></div>
            <div class="form-text">Dipakai untuk menampilkan contoh isi pada live preview.</div>
        </div>

        <div class="mb-0">
            <label class="form-label d-flex justify-content-between align-items-center">
                <span>Tombol Aksi <span class="text-muted fw-normal">(maks. 2)</span></span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="tpl-add-button"><i class="ri-add-line"></i> Tambah Tombol</button>
            </label>
            <div id="tpl-buttons-list" class="d-flex flex-column gap-2"></div>
            @error('buttons')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ============================================================
     Card 3 — Tujuan Pengiriman (kontak), dipindahkan dari form Pesan
     Terjadwal. Konsep: recipients disimpan di template ini sendiri,
     form Pesan Terjadwal tinggal pilih template + jenis pengirimannya.
============================================================ --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Kontak / Tujuan Pengiriman</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">Kosongkan kalau template ini belum ditujukan ke siapa pun — tujuan bisa diisi kapan saja lewat Edit.</p>
        @error('recipients')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <ul class="nav nav-tabs" id="tplRecipientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tpl-tab-phone-btn" data-bs-toggle="tab" data-bs-target="#tpl-tab-phone" type="button" role="tab">
                    <i class="ri-smartphone-line"></i> Nomor Tujuan
                    <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="tplCountPhone">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tpl-tab-group-btn" data-bs-toggle="tab" data-bs-target="#tpl-tab-group" type="button" role="tab">
                    <i class="ri-group-line"></i> Grup WhatsApp
                    <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="tplCountGroup">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tpl-tab-user-btn" data-bs-toggle="tab" data-bs-target="#tpl-tab-user" type="button" role="tab">
                    <i class="ri-team-line"></i> User Company
                    <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="tplCountUser">0</span>
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom-3 p-3">
            {{-- Tab 1: Nomor WhatsApp --}}
            <div class="tab-pane fade show active" id="tpl-tab-phone" role="tabpanel">
                <label class="form-label">Nomor WhatsApp Tujuan</label>
                <textarea name="phone_numbers" id="tplPhoneNumbersInput" rows="4" class="form-control"
                    placeholder="6281234567890; 6281298765432&#10;atau satu nomor per baris">{{ old('phone_numbers', $existingPhones) }}</textarea>
                <div class="form-text">Pisahkan tiap nomor dengan titik-koma (;), koma, atau baris baru.</div>
            </div>

            {{-- Tab 2: Grup WhatsApp --}}
            <div class="tab-pane fade" id="tpl-tab-group" role="tabpanel">
                <label class="form-label">Device untuk memuat daftar grup <span class="text-muted fw-normal">(tidak disimpan)</span></label>
                <select id="tplDeviceForGroups" class="wa-device-select form-select form-select-sm mb-2">
                    <option value="">Memuat device...</option>
                </select>
                <label class="form-label">Pilih Grup WhatsApp</label>
                <div id="tplGroupChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;"
                    data-selected='{!! json_encode($existingGroups) !!}'>
                    <p class="text-muted small mb-0">Pilih device di atas untuk memuat daftar grup.</p>
                </div>
            </div>

            {{-- Tab 3: User Company (Branch -> Unit -> checklist) --}}
            <div class="tab-pane fade" id="tpl-tab-user" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Branch Office</label>
                        <select id="tplUserBranchFilter" class="form-select form-select-sm">
                            <option value="">-- Semua Branch --</option>
                            @foreach($branchOffices as $branchOffice)
                                <option value="{{ $branchOffice->id }}">{{ $branchOffice->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Unit / Divisi</label>
                        <select id="tplUserUnitFilter" class="form-select form-select-sm">
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
                    <input class="form-check-input" type="checkbox" id="tplUserSelectAll">
                    <label class="form-check-label" for="tplUserSelectAll">Pilih Semua (yang tampil)</label>
                </div>

                <div id="tplUserChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;">
                    @forelse($companyMembers as $member)
                        @continue(! $member->user)
                        <div class="form-check tpl-user-checklist-item"
                            data-branch-office="{{ $member->branch_office_id ?? '' }}"
                            data-branch-office-unit="{{ $member->branch_office_unit_id ?? '' }}">
                            <input class="form-check-input tpl-user-checkbox" type="checkbox" name="user_ids[]"
                                id="tpl_member_{{ $member->user->id }}" value="{{ $member->user->id }}"
                                @checked(in_array($member->user->id, old('user_ids', $existingUsers)))
                                {{ ! $member->user->handphone ? 'disabled' : '' }}>
                            <label class="form-check-label" for="tpl_member_{{ $member->user->id }}">
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
</div>

{{-- ============================================================
     Card 4 — Status & aksi simpan.
============================================================ --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Status</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror">
                <option value="active" @selected(old('status', $template->status ?? 'active') == 'active')>Active</option>
                <option value="inactive" @selected(old('status', $template->status ?? '') == 'inactive')>Inactive</option>
            </select>
            <div class="form-text">Hanya template Active &amp; sudah lolos moderasi AI yang muncul di pilihan Pesan Terjadwal.</div>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $template ? 'Simpan Perubahan' : 'Simpan Template' }}</button>
            <a href="{{ route('chat.message-templates.index') }}" class="btn btn-light">Batal</a>
        </div>
    </div>
</div>

<template id="tpl-button-row-template">
    <div class="tpl-button-row border rounded p-2 d-flex flex-wrap gap-2 align-items-start">
        <select class="form-select form-select-sm tpl-button-type" style="max-width:130px">
            <option value="url">Buka Website</option>
            <option value="phone">Telepon</option>
        </select>
        <input type="text" class="form-control form-control-sm tpl-button-label" placeholder="Label (mis. Kunjungi Website)" maxlength="25" style="max-width:220px">
        <input type="text" class="form-control form-control-sm tpl-button-value" placeholder="https://...">
        <button type="button" class="btn btn-sm btn-outline-danger tpl-remove-button"><i class="ri-close-line"></i></button>
    </div>
</template>

<script>
(function () {
    const VAR_PATTERN = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
    const existingButtons = @json($btn ?: []);
    const existingVars = @json($varsExample ?: []);
    // Built from char codes (123 = open curly, 125 = close curly) rather
    // than literal double-curly strings, so Blade's own template compiler
    // (which scans this whole file for its echo-tag syntax) never
    // mistakes any of this JS for one of its own echo tags.
    const OPEN_BRACE = String.fromCharCode(123);
    const CLOSE_BRACE = String.fromCharCode(125);

    // Bootstrap tooltips need explicit JS init (data-bs-toggle alone
    // isn't enough in Bootstrap 5) — the (i) icon next to "Variabel".
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            new window.bootstrap.Tooltip(el);
        }
    });

    const headerEl = document.getElementById('tpl-header');
    const bodyEl = document.getElementById('tpl-body');
    const footerEl = document.getElementById('tpl-footer');
    const headerCount = document.getElementById('tpl-header-count');
    const bodyCount = document.getElementById('tpl-body-count');
    const footerCount = document.getElementById('tpl-footer-count');
    const variablesWrapper = document.getElementById('tpl-variables-wrapper');
    const variablesList = document.getElementById('tpl-variables-list');
    const buttonsList = document.getElementById('tpl-buttons-list');
    const addButtonBtn = document.getElementById('tpl-add-button');
    const buttonRowTemplate = document.getElementById('tpl-button-row-template');
    const insertVariableBtn = document.getElementById('tpl-insert-variable');

    // --- Jenis Konten: toggle link/attachment sections ---
    const contentTypeRadios = Array.prototype.slice.call(document.querySelectorAll('.tpl-content-type-radio'));
    const linkWrapper = document.getElementById('tpl-link-wrapper');
    const attachmentWrapper = document.getElementById('tpl-attachment-wrapper');

    function getContentType() {
        const checked = contentTypeRadios.filter(function (r) { return r.checked; })[0];
        return checked ? checked.value : 'text';
    }

    function syncContentType() {
        const type = getContentType();
        linkWrapper.style.display = (type === 'text_link' || type === 'text_link_file') ? '' : 'none';
        attachmentWrapper.style.display = (type === 'text_link_file') ? '' : 'none';
    }

    contentTypeRadios.forEach(function (radio) {
        radio.addEventListener('change', syncContentType);
    });
    syncContentType();

    function updateCounts() {
        headerCount.textContent = headerEl.value.length;
        bodyCount.textContent = bodyEl.value.length;
        footerCount.textContent = footerEl.value.length;
    }

    function wrapSelection(el, token) {
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const value = el.value;
        const selected = value.slice(start, end) || 'teks';
        el.value = value.slice(0, start) + token + selected + token + value.slice(end);
        el.focus();
        el.selectionStart = start + token.length;
        el.selectionEnd = start + token.length + selected.length;
        el.dispatchEvent(new Event('input'));
    }

    document.querySelectorAll('[data-wrap]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wrapSelection(bodyEl, btn.getAttribute('data-wrap'));
        });
    });

    insertVariableBtn.addEventListener('click', function () {
        const name = prompt('Nama variabel (huruf, angka, underscore saja), contoh: nama');
        if (!name) return;
        const clean = name.trim().replace(/[^a-zA-Z0-9_]/g, '');
        if (!clean) return;
        const start = bodyEl.selectionStart;
        const end = bodyEl.selectionEnd;
        const token = OPEN_BRACE + OPEN_BRACE + clean + CLOSE_BRACE + CLOSE_BRACE;
        bodyEl.value = bodyEl.value.slice(0, start) + token + bodyEl.value.slice(end);
        bodyEl.focus();
        bodyEl.selectionStart = bodyEl.selectionEnd = start + token.length;
        bodyEl.dispatchEvent(new Event('input'));
    });

    function detectedVariableNames() {
        const haystack = [headerEl.value, bodyEl.value, footerEl.value].join(' ');
        const found = [];
        let match;
        VAR_PATTERN.lastIndex = 0;
        while ((match = VAR_PATTERN.exec(haystack)) !== null) {
            if (!found.includes(match[1])) found.push(match[1]);
        }
        return found;
    }

    function rebuildVariableRows() {
        const names = detectedVariableNames();
        const currentValues = {};
        variablesList.querySelectorAll('input').forEach(function (input) {
            currentValues[input.dataset.varName] = input.value;
        });

        variablesList.innerHTML = '';
        variablesWrapper.style.display = names.length ? '' : 'none';

        names.forEach(function (name) {
            const value = currentValues[name] !== undefined
                ? currentValues[name]
                : (existingVars[name] || '');

            const row = document.createElement('div');
            row.className = 'input-group input-group-sm';

            const label = document.createElement('span');
            label.className = 'input-group-text';
            label.style.minWidth = '110px';
            label.textContent = OPEN_BRACE + OPEN_BRACE + name + CLOSE_BRACE + CLOSE_BRACE;

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control';
            input.name = 'variables_example[' + name + ']';
            input.dataset.varName = name;
            input.maxLength = 255;
            input.placeholder = 'Contoh nilai';
            input.value = value;

            row.appendChild(label);
            row.appendChild(input);
            variablesList.appendChild(row);
        });

        window.__tplUpdatePreview && window.__tplUpdatePreview();
    }

    [headerEl, bodyEl, footerEl].forEach(function (el) {
        el.addEventListener('input', function () {
            updateCounts();
            rebuildVariableRows();
        });
    });
    variablesList.addEventListener('input', function () {
        window.__tplUpdatePreview && window.__tplUpdatePreview();
    });

    function addButtonRow(data) {
        if (buttonsList.children.length >= 2) return;
        const node = buttonRowTemplate.content.cloneNode(true);
        const row = node.querySelector('.tpl-button-row');
        const typeEl = row.querySelector('.tpl-button-type');
        const labelEl = row.querySelector('.tpl-button-label');
        const valueEl = row.querySelector('.tpl-button-value');

        if (data) {
            typeEl.value = data.type || 'url';
            labelEl.value = data.label || '';
            valueEl.value = data.value || '';
        }
        valueEl.placeholder = typeEl.value === 'phone' ? '62812xxxxxxx' : 'https://...';

        typeEl.addEventListener('change', function () {
            valueEl.placeholder = typeEl.value === 'phone' ? '62812xxxxxxx' : 'https://...';
            window.__tplUpdatePreview && window.__tplUpdatePreview();
        });
        [labelEl, valueEl].forEach(function (el) {
            el.addEventListener('input', function () {
                window.__tplUpdatePreview && window.__tplUpdatePreview();
            });
        });
        row.querySelector('.tpl-remove-button').addEventListener('click', function () {
            row.remove();
            syncButtonNames();
            addButtonBtn.disabled = buttonsList.children.length >= 2;
            window.__tplUpdatePreview && window.__tplUpdatePreview();
        });

        buttonsList.appendChild(row);
        syncButtonNames();
        addButtonBtn.disabled = buttonsList.children.length >= 2;
    }

    function syncButtonNames() {
        Array.from(buttonsList.children).forEach(function (row, index) {
            row.querySelector('.tpl-button-type').setAttribute('name', 'buttons[' + index + '][type]');
            row.querySelector('.tpl-button-label').setAttribute('name', 'buttons[' + index + '][label]');
            row.querySelector('.tpl-button-value').setAttribute('name', 'buttons[' + index + '][value]');
        });
    }

    addButtonBtn.addEventListener('click', function () {
        addButtonRow(null);
        window.__tplUpdatePreview && window.__tplUpdatePreview();
    });

    // Expose read helpers for the preview panel (_preview.blade.php).
    window.__tplGetState = function () {
        return {
            header: headerEl.value,
            body: bodyEl.value,
            footer: footerEl.value,
            link: document.getElementById('tpl-link') ? document.getElementById('tpl-link').value : '',
            contentType: getContentType(),
            buttons: Array.from(buttonsList.children).map(function (row) {
                return {
                    type: row.querySelector('.tpl-button-type').value,
                    label: row.querySelector('.tpl-button-label').value,
                    value: row.querySelector('.tpl-button-value').value,
                };
            }),
            variables: Array.from(variablesList.querySelectorAll('input')).reduce(function (acc, input) {
                acc[input.dataset.varName] = input.value;
                return acc;
            }, {}),
        };
    };

    existingButtons.forEach(function (b) { addButtonRow(b); });
    updateCounts();
    rebuildVariableRows();

    document.getElementById('tpl-link') && document.getElementById('tpl-link').addEventListener('input', function () {
        window.__tplUpdatePreview && window.__tplUpdatePreview();
    });

    // --- Tujuan Pengiriman: tab badge counters ---
    const tplPhoneInput = document.getElementById('tplPhoneNumbersInput');
    const tplCountPhone = document.getElementById('tplCountPhone');
    const tplCountGroup = document.getElementById('tplCountGroup');
    const tplCountUser = document.getElementById('tplCountUser');

    function updateTplPhoneCount() {
        const items = tplPhoneInput.value.split(/[;,\r\n]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        tplCountPhone.textContent = items.length;
    }
    function updateTplGroupCount() {
        tplCountGroup.textContent = document.querySelectorAll('#tplGroupChecklist input[type=checkbox]:checked').length;
    }
    function updateTplUserCount() {
        tplCountUser.textContent = document.querySelectorAll('#tplUserChecklist input.tpl-user-checkbox:checked').length;
    }

    tplPhoneInput.addEventListener('input', updateTplPhoneCount);
    updateTplPhoneCount();
    updateTplUserCount();

    // --- Tab 2: Grup WhatsApp, loaded per selected (throwaway, unsaved) device ---
    const tplDeviceSelect = document.getElementById('tplDeviceForGroups');
    const tplGroupChecklist = document.getElementById('tplGroupChecklist');
    const tplChatsUrlTemplate = {!! json_encode(route('inbox.chats', ['device' => '__DEVICEID__'])) !!};
    const TPL_GROUP_JID_SUFFIX = '@' + 'g.us';

    function loadTplGroupsFor(deviceId) {
        if (!deviceId) {
            tplGroupChecklist.innerHTML = '<p class="text-muted small mb-0">Pilih device di atas untuk memuat daftar grup.</p>';
            updateTplGroupCount();
            return;
        }
        tplGroupChecklist.innerHTML = '<p class="text-muted small mb-0">Memuat grup WhatsApp...</p>';

        let preSelected = [];
        try { preSelected = JSON.parse(tplGroupChecklist.getAttribute('data-selected') || '[]'); } catch (e) {}

        fetch(tplChatsUrlTemplate.replace('__DEVICEID__', deviceId), { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                const groups = (data.chats || []).filter(function (c) {
                    return typeof c.chat_jid === 'string' && c.chat_jid.indexOf(TPL_GROUP_JID_SUFFIX) !== -1;
                });

                if (groups.length === 0) {
                    tplGroupChecklist.innerHTML = '<p class="text-muted small mb-0">Tidak ada grup WhatsApp pada device ini.</p>';
                    updateTplGroupCount();
                    return;
                }

                tplGroupChecklist.innerHTML = '';
                groups.forEach(function (group, idx) {
                    const checked = preSelected.indexOf(group.chat_jid) !== -1;
                    const inputId = 'tpl_group_' + idx;

                    const wrap = document.createElement('div');
                    wrap.className = 'form-check';

                    const input = document.createElement('input');
                    input.className = 'form-check-input';
                    input.type = 'checkbox';
                    input.name = 'group_jids[]';
                    input.value = group.chat_jid;
                    input.id = inputId;
                    input.checked = checked;
                    input.addEventListener('change', updateTplGroupCount);

                    const label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.setAttribute('for', inputId);
                    label.textContent = group.name || group.chat_jid;

                    wrap.appendChild(input);
                    wrap.appendChild(label);
                    tplGroupChecklist.appendChild(wrap);
                });
                updateTplGroupCount();
            })
            .catch(function () {
                tplGroupChecklist.innerHTML = '<p class="text-danger small mb-0">Gagal memuat grup WhatsApp.</p>';
                updateTplGroupCount();
            });
    }

    tplDeviceSelect.addEventListener('change', function () { loadTplGroupsFor(tplDeviceSelect.value); });

    // --- Tab 3: Company Users — branch -> unit filter + select all ---
    const tplBranchFilter = document.getElementById('tplUserBranchFilter');
    const tplUnitFilter = document.getElementById('tplUserUnitFilter');
    const tplSelectAll = document.getElementById('tplUserSelectAll');
    const tplAllUnitOptions = Array.prototype.slice.call(tplUnitFilter.querySelectorAll('option[data-branch-office]'));
    const tplUserItems = Array.prototype.slice.call(document.querySelectorAll('.tpl-user-checklist-item'));

    function filterTplUnitsByBranch() {
        const branchId = tplBranchFilter.value;
        tplAllUnitOptions.forEach(function (opt) {
            const matches = !branchId || opt.getAttribute('data-branch-office') === branchId;
            opt.hidden = !matches;
            opt.disabled = !matches;
        });
        const selected = tplUnitFilter.querySelector('option:checked');
        if (selected && selected.hasAttribute('data-branch-office') && selected.getAttribute('data-branch-office') !== branchId) {
            tplUnitFilter.value = '';
        }
    }

    function applyTplUserFilter() {
        const branchId = tplBranchFilter.value;
        const unitId = tplUnitFilter.value;

        tplUserItems.forEach(function (item) {
            const matchesBranch = !branchId || item.getAttribute('data-branch-office') === branchId;
            const matchesUnit = !unitId || item.getAttribute('data-branch-office-unit') === unitId;
            item.style.display = (matchesBranch && matchesUnit) ? '' : 'none';
        });

        tplSelectAll.checked = false;
    }

    tplBranchFilter.addEventListener('change', function () { filterTplUnitsByBranch(); applyTplUserFilter(); });
    tplUnitFilter.addEventListener('change', applyTplUserFilter);

    tplSelectAll.addEventListener('change', function () {
        tplUserItems.forEach(function (item) {
            if (item.style.display === 'none') return;
            const checkbox = item.querySelector('.tpl-user-checkbox');
            if (checkbox && !checkbox.disabled) checkbox.checked = tplSelectAll.checked;
        });
        updateTplUserCount();
    });

    document.querySelectorAll('.tpl-user-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateTplUserCount);
    });
})();
</script>
