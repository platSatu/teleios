@extends('layouts.dashboard')

@section('content')
<div class="row g-3">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('form.branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.category.index', ['branch_office_id' => $header->branch_office_id]) }}">Form Category</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.header.index', $header->form_category_id) }}">{{ $header->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pertanyaan</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Pertanyaan — {{ $header->name }}</h4>
                <p class="text-muted mb-0">Klik "Tambah Pertanyaan" — baris baru langsung muncul di bawah, langsung diisi di tempat (tanpa popup). Urutan otomatis mengikuti waktu dibuat.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('form.submission.index', $header->id) }}" class="btn btn-light">
                    <i class="ri-inbox-line"></i> Submission
                </a>
                <a href="{{ route('form.public.show', $header->slug) }}" target="_blank" class="btn btn-light">
                    <i class="ri-external-link-line"></i> Lihat Form Publik
                </a>
                <a href="{{ route('form.header.index', $header->form_category_id) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <button type="button" class="btn btn-primary" id="fc-add-btn">
                    <i class="ri-add-line"></i> Tambah Pertanyaan
                </button>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div id="fc-list" class="d-flex flex-column gap-2"></div>
        <div class="card">
            <p class="text-muted text-center py-4 mb-0" id="fc-empty" style="display:none;">
                Belum ada pertanyaan. Klik "Tambah Pertanyaan" di atas untuk membuat field pertama form ini.
            </p>
            <p class="text-muted text-center py-4 mb-0" id="fc-loading">Memuat...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const listUrl = {{ \Illuminate\Support\Js::from(route('form.content.list', $header->id)) }};
    const storeUrl = {{ \Illuminate\Support\Js::from(route('form.content.store', $header->id)) }};
    const updateUrlTemplate = {{ \Illuminate\Support\Js::from(route('form.content.update', [$header->id, '__CONTENT__'])) }};
    const destroyUrlTemplate = {{ \Illuminate\Support\Js::from(route('form.content.destroy', [$header->id, '__CONTENT__'])) }};
    const csrfToken = {{ \Illuminate\Support\Js::from(csrf_token()) }};

    const listEl = document.getElementById('fc-list');
    const emptyEl = document.getElementById('fc-empty');
    const loadingEl = document.getElementById('fc-loading');
    const addBtn = document.getElementById('fc-add-btn');

    const TYPE_META = {
        single_line: { icon: 'ri-text', badge: 'bg-primary-subtle text-primary', label: 'Single Line' },
        textarea: { icon: 'ri-align-left', badge: 'bg-info-subtle text-info', label: 'Textarea' },
        single_choice: { icon: 'ri-radio-button-line', badge: 'bg-warning-subtle text-warning', label: 'Single Choice' },
        multiple_choice: { icon: 'ri-checkbox-multiple-line', badge: 'bg-warning-subtle text-warning', label: 'Multiple Choice' },
        file_upload: { icon: 'ri-upload-2-line', badge: 'bg-secondary-subtle text-secondary', label: 'File Upload' },
    };
    const CHOICE_TYPES = ['single_choice', 'multiple_choice'];

    let contents = [];
    // Cuma SATU kartu yang boleh dalam mode edit/tambah di satu waktu --
    // menyederhanakan sinkronisasi state (lihat catatan di save()) dan
    // meniru perilaku umum form builder: fokus satu pertanyaan dulu
    // sebelum pindah ke yang lain.
    // { isNew: bool, index: number|null (index di array `contents` kalau edit existing), data: {...} }
    let editing = null;

    function fetchJson(url, options) {
        options = options || {};
        const headers = Object.assign({ 'Accept': 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
        return fetch(url, Object.assign({}, options, { headers: headers }))
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, status: res.status, body: body }; }); });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function blankDraft() {
        return { name: '', type: 'single_line', options: [''], allowed_file_types: '', is_required: true };
    }

    function loadList() {
        loadingEl.style.display = '';
        listEl.innerHTML = '';
        emptyEl.style.display = 'none';

        return fetchJson(listUrl).then(function (res) {
            loadingEl.style.display = 'none';
            contents = (res.body && res.body.contents) || [];
            render();
        }).catch(function () {
            loadingEl.textContent = 'Gagal memuat pertanyaan. Muat ulang halaman.';
        });
    }

    function render() {
        listEl.innerHTML = '';

        if (contents.length === 0 && !(editing && editing.isNew)) {
            emptyEl.style.display = '';
        } else {
            emptyEl.style.display = 'none';
        }

        contents.forEach(function (content, index) {
            const isEditingThis = editing && !editing.isNew && editing.index === index;
            listEl.appendChild(isEditingThis ? buildEditCard(editing.data, index, false) : buildViewCard(content, index));
        });

        if (editing && editing.isNew) {
            listEl.appendChild(buildEditCard(editing.data, null, true));
        }

        addBtn.disabled = !!editing;
    }

    function buildViewCard(content, index) {
        const meta = TYPE_META[content.type] || TYPE_META.single_line;
        const row = document.createElement('div');
        row.className = 'card mb-0';

        let optionsHtml = '';
        if (CHOICE_TYPES.indexOf(content.type) !== -1 && Array.isArray(content.options) && content.options.length) {
            optionsHtml = '<div class="mt-2 d-flex flex-wrap gap-1">' + content.options.map(function (opt) {
                return '<span class="badge bg-light text-dark border fw-normal">' + escapeHtml(opt) + '</span>';
            }).join('') + '</div>';
        }
        if (content.type === 'file_upload') {
            optionsHtml = '<div class="text-muted fs-12 mt-1">Tipe file: ' + escapeHtml(content.allowed_file_types || 'pdf,jpg,jpeg,png') + '</div>';
        }

        row.innerHTML =
            '<div class="card-body d-flex align-items-start justify-content-between gap-2 flex-wrap">' +
                '<div class="d-flex align-items-start gap-2">' +
                    '<span class="badge bg-light text-dark border rounded-circle fs-12" style="width:26px;height:26px;" title="Urutan">' + (index + 1) + '</span>' +
                    '<div>' +
                        '<div class="fw-semibold">' + escapeHtml(content.name) + (content.is_required ? ' <span class="text-danger">*</span>' : '') + '</div>' +
                        '<span class="badge ' + meta.badge + '"><i class="' + meta.icon + ' align-middle"></i> ' + meta.label + '</span>' +
                        optionsHtml +
                    '</div>' +
                '</div>' +
                '<div class="d-flex flex-nowrap gap-1">' +
                    '<button type="button" class="btn btn-sm btn-light fc-edit-btn" title="Edit"><i class="ri-edit-line"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-light text-danger fc-delete-btn" title="Hapus"><i class="ri-delete-bin-line"></i></button>' +
                '</div>' +
            '</div>';

        row.querySelector('.fc-edit-btn').addEventListener('click', function () {
            editing = { isNew: false, index: index, data: Object.assign({}, content, { options: Array.isArray(content.options) && content.options.length ? content.options.slice() : [''] }) };
            render();
            focusFirstField();
        });
        row.querySelector('.fc-delete-btn').addEventListener('click', function () { deleteContent(content); });

        return row;
    }

    function buildEditCard(data, index, isNew) {
        const card = document.createElement('div');
        card.className = 'card border-primary mb-0';
        card.id = 'fc-edit-card';

        const isChoice = CHOICE_TYPES.indexOf(data.type) !== -1;

        const optionsRowsHtml = data.options.map(function (opt, i) {
            return '<div class="input-group input-group-sm mb-2 fc-option-row" data-option-index="' + i + '">' +
                '<input type="text" class="form-control fc-option-input" value="' + escapeHtml(opt) + '" placeholder="Isi pilihan...">' +
                '<button type="button" class="btn btn-outline-danger fc-option-remove"><i class="ri-close-line"></i></button>' +
            '</div>';
        }).join('');

        card.innerHTML =
            '<div class="card-body">' +
                '<div class="row g-3">' +
                    '<div class="col-12 col-md-7">' +
                        '<label class="form-label">Nama Pertanyaan</label>' +
                        '<input type="text" class="form-control fc-input-name" placeholder="Misal: Nama Lengkap, Alamat Email" value="' + escapeHtml(data.name) + '">' +
                    '</div>' +
                    '<div class="col-12 col-md-5">' +
                        '<label class="form-label">Tipe Jawaban</label>' +
                        '<select class="form-select fc-input-type">' +
                            '<option value="single_line"' + (data.type === 'single_line' ? ' selected' : '') + '>Single Line</option>' +
                            '<option value="textarea"' + (data.type === 'textarea' ? ' selected' : '') + '>Textarea</option>' +
                            '<option value="single_choice"' + (data.type === 'single_choice' ? ' selected' : '') + '>Single Choice</option>' +
                            '<option value="multiple_choice"' + (data.type === 'multiple_choice' ? ' selected' : '') + '>Multiple Choice</option>' +
                            '<option value="file_upload"' + (data.type === 'file_upload' ? ' selected' : '') + '>File Upload</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +

                (isChoice ?
                    '<div class="mt-3 fc-options-wrap">' +
                        '<label class="form-label">Pilihan Jawaban</label>' +
                        '<div class="fc-options-list">' + optionsRowsHtml + '</div>' +
                        '<button type="button" class="btn btn-sm btn-light fc-option-add"><i class="ri-add-line"></i> Tambah Pilihan</button>' +
                    '</div>'
                : '') +

                (data.type === 'file_upload' ?
                    '<div class="mt-3">' +
                        '<label class="form-label">Tipe File yang Diizinkan</label>' +
                        '<input type="text" class="form-control fc-input-file-types" value="' + escapeHtml(data.allowed_file_types || '') + '" placeholder="pdf,jpg,jpeg,png">' +
                        '<div class="form-text">Pisahkan dengan koma. Kosongkan untuk pakai default: pdf,jpg,jpeg,png.</div>' +
                    '</div>'
                : '') +

                '<div class="form-check form-switch mt-3">' +
                    '<input type="checkbox" class="form-check-input fc-input-required"' + (data.is_required ? ' checked' : '') + '>' +
                    '<label class="form-check-label">Wajib diisi</label>' +
                '</div>' +

                '<div class="text-danger fs-12 mt-2 fc-edit-error" style="display:none;"></div>' +

                '<div class="d-flex gap-2 mt-3">' +
                    '<button type="button" class="btn btn-primary btn-sm fc-save-btn"><i class="ri-check-line"></i> Simpan</button>' +
                    '<button type="button" class="btn btn-light btn-sm fc-cancel-btn">Batal</button>' +
                '</div>' +
            '</div>';

        // Input teks/checkbox: update state LANGSUNG tanpa render() ulang,
        // supaya fokus/kursor di input tidak hilang saat mengetik (lihat
        // catatan di deklarasi `editing` di atas).
        card.querySelector('.fc-input-name').addEventListener('input', function (e) { data.name = e.target.value; });
        card.querySelector('.fc-input-required').addEventListener('change', function (e) { data.is_required = e.target.checked; });

        const fileTypesInput = card.querySelector('.fc-input-file-types');
        if (fileTypesInput) {
            fileTypesInput.addEventListener('input', function (e) { data.allowed_file_types = e.target.value; });
        }

        card.querySelectorAll('.fc-option-input').forEach(function (input, i) {
            input.addEventListener('input', function (e) { data.options[i] = e.target.value; });
        });

        // Ganti tipe / tambah-hapus opsi mengubah field mana yang tampil
        // -- ini re-render (via updateEditingCard di bawah), tapi cuma
        // dipicu oleh klik/pilih (bukan ketikan), jadi aman dari masalah
        // fokus di atas.
        card.querySelector('.fc-input-type').addEventListener('change', function (e) {
            data.type = e.target.value;
            if (CHOICE_TYPES.indexOf(data.type) !== -1 && (!data.options || data.options.length === 0)) {
                data.options = [''];
            }
            render();
            focusFirstField();
        });

        card.querySelectorAll('.fc-option-remove').forEach(function (btn, i) {
            btn.addEventListener('click', function () {
                data.options.splice(i, 1);
                if (data.options.length === 0) data.options.push('');
                render();
            });
        });

        const addOptionBtn = card.querySelector('.fc-option-add');
        if (addOptionBtn) {
            addOptionBtn.addEventListener('click', function () {
                data.options.push('');
                render();
            });
        }

        card.querySelector('.fc-save-btn').addEventListener('click', function () { save(data, index, isNew, card); });
        card.querySelector('.fc-cancel-btn').addEventListener('click', function () {
            editing = null;
            render();
        });

        return card;
    }

    function focusFirstField() {
        setTimeout(function () {
            const el = document.getElementById('fc-edit-card');
            const nameInput = el && el.querySelector('.fc-input-name');
            if (nameInput) nameInput.focus();
        }, 0);
    }

    function showEditError(card, message) {
        const errEl = card.querySelector('.fc-edit-error');
        errEl.textContent = message;
        errEl.style.display = '';
    }

    function save(data, index, isNew, card) {
        const isChoice = CHOICE_TYPES.indexOf(data.type) !== -1;
        const options = (data.options || []).map(function (v) { return (v || '').trim(); }).filter(function (v) { return v.length > 0; });

        if (!data.name || !data.name.trim()) {
            showEditError(card, 'Nama pertanyaan wajib diisi.');
            return;
        }
        if (isChoice && options.length === 0) {
            showEditError(card, 'Pertanyaan pilihan wajib memiliki minimal 1 opsi jawaban.');
            return;
        }

        const payload = {
            name: data.name.trim(),
            type: data.type,
            is_required: !!data.is_required,
        };
        if (isChoice) payload.options = options;
        if (data.type === 'file_upload' && data.allowed_file_types && data.allowed_file_types.trim()) {
            payload.allowed_file_types = data.allowed_file_types.trim();
        }

        const url = isNew ? storeUrl : updateUrlTemplate.replace('__CONTENT__', contents[index].id);
        const method = isNew ? 'POST' : 'PUT';

        fetchJson(url, {
            method: method,
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload),
        }).then(function (res) {
            if (!res.ok) {
                const firstError = res.body && res.body.errors ? Object.values(res.body.errors)[0][0] : (res.body && res.body.message) || 'Gagal menyimpan pertanyaan.';
                showEditError(card, firstError);
                return;
            }
            editing = null;
            loadList();
        });
    }

    function deleteContent(content) {
        if (!confirm('Hapus pertanyaan "' + content.name + '"? Jawaban yang sudah masuk untuk pertanyaan ini tetap tersimpan.')) {
            return;
        }
        fetchJson(destroyUrlTemplate.replace('__CONTENT__', content.id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
        }).then(function () { loadList(); });
    }

    addBtn.addEventListener('click', function () {
        if (editing) return;
        editing = { isNew: true, index: null, data: blankDraft() };
        render();
        focusFirstField();
        listEl.scrollIntoView({ behavior: 'smooth', block: 'end' });
    });

    loadList();
});
</script>
@endsection
