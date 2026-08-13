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
    $existingPhoneValues = $existingRecipients->where('type', 'phone')->pluck('value')->all();
    $existingGroups = $existingRecipients->where('type', 'group')->pluck('value')->all();
    $existingUsers = $existingRecipients->where('type', 'user')->pluck('value')->all();
    $currentType = old('type', $schedule->type ?? 'recurring');

    // "Grup Buku Telepon" tab pre-check on edit: recipients only ever
    // store a plain phone number (type=phone), not which WaPhoneBook it
    // came from, so a contact is shown as checked if its saved number is
    // already among this schedule's phone-type recipients. Best-effort —
    // if the number was retyped in a different format it won't match —
    // but keeps this tab from needing its own storage just to remember
    // which contacts were originally picked here vs typed in Tab 1.
    $oldPhonebookIds = old('phonebook_ids');
    $checkedPhonebookByPhone = is_array($oldPhonebookIds) ? [] : $existingPhoneValues;
    $hasAnyPhoneBookContact = $phoneBookCategories->contains(fn ($c) => $c->phoneBooks->isNotEmpty());

    // Pre-built here as plain PHP so the JS block below only ever has
    // to embed a single already-computed variable — keeps every raw
    // echo in this file to a simple one-liner instead of a multi-line
    // expression. `recipients` travels along too — see the "Tujuan
    // Pengiriman" section further down: when "Gunakan Template" is on,
    // that section switches from the editable tri-tab to a read-only
    // summary of whichever template is selected, since recipients now
    // live on the template itself (Chat\MessageTemplateController).
    $templatesForJs = $templates->map(function ($t) {
        $recipients = collect($t->recipients ?? []);

        return [
            'id' => $t->id,
            'name' => $t->name,
            'template' => $t->template,
            'recipients' => [
                'phone' => $recipients->where('type', 'phone')->count(),
                'group' => $recipients->where('type', 'group')->count(),
                'user' => $recipients->where('type', 'user')->count(),
            ],
        ];
    })->values();

    $existingAttachmentName = $schedule->attachment_original_name ?? null;
@endphp

{{-- ============================================================
     Jenis Pengiriman — sengaja jadi field PALING ATAS & satu-satunya
     yang memicu bagian lain (Isi Pesan vs Langkah Pesan, label
     tanggal, dsb) lewat syncTypeSections() di bawah. Kartu, bukan
     <select> polos, supaya pilihan ini benar-benar terasa sebagai
     keputusan utama form ("mau kirim dengan cara apa?") sebelum
     mengisi apa pun yang lain — sesuai referensi tampilan yang
     diminta.
============================================================ --}}
<div class="card border mb-3">
    <div class="card-body">
        <label class="form-label d-block mb-2">Jenis Pengiriman</label>
        <div class="row g-2" id="scheduleTypeCards">
            <div class="col-sm-4">
                <input type="radio" class="btn-check schedule-type-radio" name="type" id="scheduleType-once"
                    value="once" autocomplete="off" @checked($currentType == 'once')>
                <label class="btn btn-outline-primary w-100 h-100 text-start p-3 schedule-type-card" for="scheduleType-once">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="ri-send-plane-line fs-4"></i>
                        <span class="fw-semibold">Sekali Kirim</span>
                    </div>
                    <div class="small text-body-secondary">Broadcast langsung atau terjadwal, satu kali di satu tanggal &amp; jam.</div>
                </label>
            </div>
            <div class="col-sm-4">
                <input type="radio" class="btn-check schedule-type-radio" name="type" id="scheduleType-recurring"
                    value="recurring" autocomplete="off" @checked($currentType == 'recurring')>
                <label class="btn btn-outline-primary w-100 h-100 text-start p-3 schedule-type-card" for="scheduleType-recurring">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="ri-repeat-line fs-4"></i>
                        <span class="fw-semibold">Berulang</span>
                    </div>
                    <div class="small text-body-secondary">Terkirim otomatis setiap hari, dalam rentang tanggal yang dipilih.</div>
                </label>
            </div>
            <div class="col-sm-4">
                <input type="radio" class="btn-check schedule-type-radio" name="type" id="scheduleType-drip"
                    value="drip" autocomplete="off" @checked($currentType == 'drip')>
                <label class="btn btn-outline-primary w-100 h-100 text-start p-3 schedule-type-card" for="scheduleType-drip">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="ri-flow-chart fs-4"></i>
                        <span class="fw-semibold">Bertahap per Kontak</span>
                    </div>
                    <div class="small text-body-secondary">Beberapa pesan berjarak hari (drip), sama untuk semua tujuan.</div>
                </label>
            </div>
        </div>
        @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<style>
    /* Bootstrap's .btn-check:checked + .btn-outline-primary turns the
       label's own text white automatically (that's why "Sekali Kirim"
       above is readable), but it doesn't touch descendant elements that
       carry their own text-color utility class — .text-body-secondary
       on the description line keeps its normal gray regardless of the
       red/primary background behind it. This targets just that one
       element only while its radio is actually checked, instead of
       overriding it everywhere. */
    .schedule-type-radio:checked + .schedule-type-card .text-body-secondary {
        color: rgba(255, 255, 255, .85) !important;
    }
</style>

<div class="card border mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <label class="form-label">Device</label>
                <select name="device_id" id="scheduleDeviceSelect" class="wa-device-select form-select @error('device_id') is-invalid @enderror"
                    data-selected="{{ old('device_id', $schedule->device_id ?? '') }}" required>
                    <option value="">Memuat device...</option>
                </select>
                @error('device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Nama / Judul</label>
                <input type="text" name="title" value="{{ old('title', $schedule->title ?? '') }}"
                    class="form-control @error('title') is-invalid @enderror" placeholder="Contoh: Reminder Tagihan Bulanan" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
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
                <select name="category_schedule" id="categoryScheduleSelect" class="form-select @error('category_schedule') is-invalid @enderror">
                    <option value="text" @selected(old('category_schedule', $schedule->category_schedule ?? 'text') == 'text')>Text</option>
                    <option value="location" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'location')>Location</option>
                    <option value="image" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'image')>Image</option>
                    <option value="document" @selected(old('category_schedule', $schedule->category_schedule ?? '') == 'document')>Document</option>
                </select>
                @error('category_schedule')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- text: isi pesan saja. location: dipakai sebagai "Nama
                 Lokasi" (JS mengubah label/placeholder/rows-nya) + field
                 Link di bawah. image/document: disembunyikan, ganti ke
                 upload file. --}}
            <div class="mb-3" id="categoryMessageWrapper">
                <label class="form-label" id="categoryMessageLabel">Isi Pesan</label>
                <textarea name="message" id="categoryMessageInput" rows="4" class="form-control @error('message') is-invalid @enderror"
                    placeholder="Tulis isi pesan...">{{ old('message', $schedule->message ?? '') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Muncul untuk kategori location --}}
            <div class="mb-3" id="categoryLinkWrapper" style="display:none;">
                <label class="form-label">Link Lokasi</label>
                <input type="text" name="link" maxlength="2000" value="{{ old('link', $schedule->link ?? '') }}"
                    class="form-control @error('link') is-invalid @enderror" placeholder="https://maps.google.com/...">
                @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Muncul untuk kategori image/document --}}
            <div class="mb-0" id="categoryAttachmentWrapper" style="display:none;">
                <label class="form-label">Upload File</label>
                @if ($schedule && $schedule->attachment_path)
                    <div class="d-flex align-items-center gap-2 border rounded p-2 mb-2 bg-light-subtle">
                        <i class="ri-file-3-line fs-4"></i>
                        <div class="flex-grow-1 small">
                            <a href="{{ asset('storage/'.$schedule->attachment_path) }}" target="_blank">{{ $schedule->attachment_original_name }}</a>
                            <div class="text-muted">{{ number_format(($schedule->attachment_size ?? 0) / 1024, 0) }} KB</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="scheduleRemoveAttachment">
                            <label class="form-check-label small text-danger" for="scheduleRemoveAttachment">Hapus</label>
                        </div>
                    </div>
                @endif
                <input type="file" name="attachment" id="categoryAttachmentInput" class="form-control @error('attachment') is-invalid @enderror">
                <div class="form-text" id="categoryAttachmentHint"></div>
                @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     Jadwal: tanggal + jam. Label & kolom "Tanggal Berakhir"
     menyesuaikan Jenis Pengiriman lewat JS di bawah.
============================================================ --}}
<div class="card border mb-3">
    <div class="card-body">
        <label class="form-label d-block mb-2">Jadwal</label>
        <div class="row" id="dateFieldsRow">
            <div class="col-md-4 mb-3 mb-md-0">
                <label class="form-label" id="dateStartLabel">Tanggal Mulai</label>
                <input type="date" name="date_start"
                    value="{{ old('date_start', isset($schedule->date_start) ? $schedule->date_start->format('Y-m-d') : '') }}"
                    class="form-control @error('date_start') is-invalid @enderror" required>
                @error('date_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 mb-3 mb-md-0" id="dateEndCol">
                <label class="form-label">Tanggal Berakhir</label>
                <input type="date" name="date_end"
                    value="{{ old('date_end', isset($schedule->date_end) ? $schedule->date_end->format('Y-m-d') : '') }}"
                    class="form-control @error('date_end') is-invalid @enderror">
                <div class="form-text">Kosongkan kalau pesan cuma dikirim 1 hari. Isi untuk kirim berulang setiap hari sampai tanggal ini.</div>
                @error('date_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" id="scheduleTimeLabel">Jam Kirim</label>
                <input type="time" name="schedule_time" value="{{ old('schedule_time', isset($schedule->schedule_time) ? \Illuminate\Support\Carbon::parse($schedule->schedule_time)->format('H:i') : '') }}"
                    class="form-control @error('schedule_time') is-invalid @enderror" required>
                @error('schedule_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
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
     Tujuan Pengiriman: 3 tab (nomor / grup WA / user company) — hanya
     untuk 'drip' atau saat "Gunakan Template" OFF. Saat template
     dipakai, tujuan sudah ikut tersimpan di template itu sendiri (lihat
     Chat\MessageTemplateController), jadi bagian ini diganti ringkasan
     read-only supaya tidak terlihat seperti input yang harus diisi
     ulang.
============================================================ --}}
<div class="card border mb-3">
    <div class="card-body">
        <div class="alert alert-light border d-none mb-3" id="recipientFromTemplateNotice">
            <div class="fw-semibold mb-1"><i class="ri-file-list-3-line"></i> Tujuan dari Template</div>
            <div id="recipientFromTemplateSummary" class="small text-muted">Pilih template di atas untuk melihat tujuannya.</div>
            <div class="small mt-1">Mau ubah tujuan? <a href="{{ route('chat.message-templates.index') }}" target="_blank">Edit di halaman WA Template</a>.</div>
        </div>

        <div id="recipientSection">
            <label class="form-label d-block">Tujuan Pengiriman</label>
            @error('recipients')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            @error('phonebook_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

            <ul class="nav nav-tabs flex-wrap" id="recipientTabs" role="tablist">
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
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-phonebook-btn" data-bs-toggle="tab" data-bs-target="#tab-phonebook" type="button" role="tab">
                        <i class="ri-contacts-book-2-line"></i> Grup Buku Telepon
                        <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" id="countPhonebook">0</span>
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
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <label class="form-label mb-0">Pilih Grup WhatsApp</label>
                        <div class="position-relative" style="max-width:260px; width:100%;">
                            <i class="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2 text-muted small"></i>
                            <input type="text" id="groupSearchInput" class="form-control form-control-sm ps-4" placeholder="Cari grup...">
                        </div>
                    </div>
                    <div id="groupChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;"
                        data-selected='{!! json_encode($existingGroups) !!}'>
                        <p class="text-muted small mb-0">Pilih device terlebih dahulu untuk memuat daftar grup.</p>
                    </div>
                    <p class="text-muted small mb-0 mt-1 d-none" id="groupSearchEmpty">Tidak ada grup yang cocok dengan pencarian.</p>
                </div>

                {{-- Tab 3: User Company (Branch -> Unit -> search -> checklist) --}}
                <div class="tab-pane fade" id="tab-user" role="tabpanel">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Branch Office</label>
                            <select id="userBranchFilter" class="form-select form-select-sm">
                                <option value="">-- Semua Branch --</option>
                                @foreach($branchOffices as $branchOffice)
                                    <option value="{{ $branchOffice->id }}">{{ $branchOffice->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <label class="form-label">Cari User</label>
                            <input type="text" id="userSearchInput" class="form-control form-control-sm" placeholder="Cari nama...">
                        </div>
                    </div>

                    <div class="form-check my-2">
                        <input class="form-check-input" type="checkbox" id="userSelectAll">
                        <label class="form-check-label" for="userSelectAll">Pilih Semua (yang tampil)</label>
                    </div>

                    <div id="userChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;">
                        @forelse($companyMembers as $member)
                            @continue(! $member->user)
                            <div class="form-check user-checklist-item"
                                data-branch-office="{{ $member->branch_office_id ?? '' }}"
                                data-branch-office-unit="{{ $member->branch_office_unit_id ?? '' }}"
                                data-search="{{ \Illuminate\Support\Str::lower($member->user->name) }}">
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
                    <p class="text-muted small mb-0 mt-1 d-none" id="userSearchEmpty">Tidak ada user yang cocok dengan pencarian.</p>
                    @error('user_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                {{-- Tab 4: Grup Buku Telepon (Kelompok -> search -> checklist) --}}
                <div class="tab-pane fade" id="tab-phonebook" role="tabpanel">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Kelompok</label>
                            <select id="phonebookCategoryFilter" class="form-select form-select-sm">
                                <option value="">-- Semua Kelompok --</option>
                                @foreach($phoneBookCategories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cari Kontak</label>
                            <input type="text" id="phonebookSearchInput" class="form-control form-control-sm" placeholder="Cari nama atau nomor...">
                        </div>
                    </div>

                    <div class="form-check my-2">
                        <input class="form-check-input" type="checkbox" id="phonebookSelectAll">
                        <label class="form-check-label" for="phonebookSelectAll">Pilih Semua (yang tampil)</label>
                    </div>

                    <div id="phonebookChecklist" class="border rounded p-2" style="max-height:260px;overflow-y:auto;">
                        {{-- Nested per-category (not a flatMap'd single loop) so
                             $category->name is already in hand from
                             formData()'s eager load — reading $contact->category
                             instead would lazy-load a query per contact. --}}
                        @if($hasAnyPhoneBookContact)
                            @foreach($phoneBookCategories as $category)
                                @foreach($category->phoneBooks as $contact)
                                    <div class="form-check phonebook-checklist-item"
                                        data-category="{{ $category->id }}"
                                        data-search="{{ \Illuminate\Support\Str::lower($contact->name.' '.$contact->phone) }}">
                                        <input class="form-check-input phonebook-checkbox" type="checkbox" name="phonebook_ids[]"
                                            id="phonebook_{{ $contact->id }}" value="{{ $contact->id }}"
                                            @checked(in_array($contact->id, (array) $oldPhonebookIds) || in_array($contact->phone, $checkedPhonebookByPhone))
                                            {{ ($contact->is_blacklisted || ! $contact->phone) ? 'disabled' : '' }}>
                                        <label class="form-check-label" for="phonebook_{{ $contact->id }}">
                                            {{ $contact->name }}
                                            <span class="text-muted small">
                                                — {{ $category->name }}
                                                {{ $contact->is_blacklisted ? '(blacklist)' : (! $contact->phone ? '(tanpa nomor)' : '') }}
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            @endforeach
                        @else
                            <p class="text-muted small mb-0">Belum ada kontak di Buku Telepon. Tambahkan dari menu <a href="{{ route('chat.phone-books.index') }}" target="_blank">Buku Telepon</a>.</p>
                        @endif
                    </div>
                    <p class="text-muted small mb-0 mt-1 d-none" id="phonebookSearchEmpty">Tidak ada kontak yang cocok dengan pencarian.</p>
                    <div class="form-text">Kontak yang dicentang otomatis dikirim sebagai nomor WhatsApp tujuan — digabung &amp; tidak diduplikasi dengan tab "Nomor Tujuan".</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border mb-3">
    <div class="card-body">
        <label class="form-label">Status</label>
        <select name="status" class="form-select @error('status') is-invalid @enderror">
            <option value="active" @selected(old('status', $schedule->status ?? 'active') == 'active')>Active</option>
            <option value="inactive" @selected(old('status', $schedule->status ?? '') == 'inactive')>Inactive</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
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
    // A btn-check radio group, not a <select> — getType()/setType() below
    // are the only two places that need to know that, everything else
    // still just calls getType() the same way it would call .value on a
    // <select>.
    var typeRadios = Array.prototype.slice.call(document.querySelectorAll('.schedule-type-radio'));
    var contentSection = document.getElementById('contentSection');
    var dripStepsSection = document.getElementById('dripStepsSection');
    var dateStartLabel = document.getElementById('dateStartLabel');
    var dateEndCol = document.getElementById('dateEndCol');
    var scheduleTimeLabel = document.getElementById('scheduleTimeLabel');

    function getType() {
        var checked = typeRadios.filter(function (r) { return r.checked; })[0];
        return checked ? checked.value : 'recurring';
    }

    function syncTypeSections() {
        var type = getType();

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

        // Drip needs at least one step row present the moment it becomes
        // the active type (e.g. user switches to it after page load,
        // not just on initial render — see existingSteps handling below
        // for the initial-load case).
        if (type === 'drip' && stepsContainer && stepsContainer.children.length === 0) {
            addStepRow();
        }
    }

    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', syncTypeSections);
    });

    // --- Template vs manual message toggle (jenis once/recurring) ---
    var useTemplateToggle = document.getElementById('useTemplateToggle');
    var templateFields = document.getElementById('templateFields');
    var manualFields = document.getElementById('manualFields');
    var templateSelect = templateFields.querySelector('select[name="wa_message_template_id"]');
    var templatePreview = document.getElementById('templatePreview');
    var recipientSection = document.getElementById('recipientSection');
    var recipientFromTemplateNotice = document.getElementById('recipientFromTemplateNotice');
    var recipientFromTemplateSummary = document.getElementById('recipientFromTemplateSummary');
    var templatesData = @json($templatesForJs);

    function syncTemplateToggle() {
        var on = useTemplateToggle.checked;
        templateFields.style.display = on ? '' : 'none';
        manualFields.style.display = on ? 'none' : '';

        // Recipients only come from the tri-tab below for 'drip' or a
        // manual (non-template) once/recurring message — a template in
        // use already carries its own recipients (see
        // Chat\MessageTemplateController), so showing an empty tri-tab
        // here on top of that would just be confusing/redundant.
        var usesTemplateRecipients = on && getType() !== 'drip';
        recipientSection.classList.toggle('d-none', usesTemplateRecipients);
        recipientFromTemplateNotice.classList.toggle('d-none', !usesTemplateRecipients);
    }

    function syncTemplateRecipientsSummary() {
        var tpl = templatesData.filter(function (t) { return t.id === templateSelect.value; })[0];
        if (!tpl) {
            recipientFromTemplateSummary.textContent = 'Pilih template di atas untuk melihat tujuannya.';
            return;
        }
        var r = tpl.recipients || { phone: 0, group: 0, user: 0 };
        var parts = [];
        if (r.phone) parts.push(r.phone + ' nomor');
        if (r.group) parts.push(r.group + ' grup');
        if (r.user) parts.push(r.user + ' user company');
        recipientFromTemplateSummary.textContent = parts.length
            ? 'Terkirim ke: ' + parts.join(', ') + '.'
            : 'Template ini belum punya tujuan tersimpan — atur dulu di halaman WA Template.';
    }

    function syncTemplatePreview() {
        var opt = templateSelect.options[templateSelect.selectedIndex];
        templatePreview.textContent = (opt && opt.getAttribute('data-preview')) || 'Pilih template untuk melihat isi pesannya di sini.';
        syncTemplateRecipientsSummary();
    }

    useTemplateToggle.addEventListener('change', syncTemplateToggle);
    templateSelect.addEventListener('change', syncTemplatePreview);
    syncTemplateToggle();
    syncTemplatePreview();

    // --- Kategori (manual, non-template): text / location / image / document ---
    var categorySelect = document.getElementById('categoryScheduleSelect');
    var categoryMessageWrapper = document.getElementById('categoryMessageWrapper');
    var categoryMessageLabel = document.getElementById('categoryMessageLabel');
    var categoryMessageInput = document.getElementById('categoryMessageInput');
    var categoryLinkWrapper = document.getElementById('categoryLinkWrapper');
    var categoryAttachmentWrapper = document.getElementById('categoryAttachmentWrapper');
    var categoryAttachmentInput = document.getElementById('categoryAttachmentInput');
    var categoryAttachmentHint = document.getElementById('categoryAttachmentHint');

    var CATEGORY_ATTACHMENT_ACCEPT = {
        image: '.jpg,.jpeg,.png',
        document: '.xlsx,.xls,.docx,.doc,.pdf',
    };
    var CATEGORY_ATTACHMENT_HINT = {
        image: 'Format: JPG, JPEG, PNG. Maks. 5MB.',
        document: 'Format: XLSX, XLS, DOCX, DOC, PDF. Maks. 10MB.',
    };

    function syncManualCategory() {
        var category = categorySelect.value;

        if (category === 'text') {
            categoryMessageWrapper.style.display = '';
            categoryMessageLabel.textContent = 'Isi Pesan';
            categoryMessageInput.rows = 4;
            categoryMessageInput.placeholder = 'Tulis isi pesan...';
            categoryLinkWrapper.style.display = 'none';
            categoryAttachmentWrapper.style.display = 'none';
        } else if (category === 'location') {
            categoryMessageWrapper.style.display = '';
            categoryMessageLabel.textContent = 'Nama Lokasi';
            categoryMessageInput.rows = 1;
            categoryMessageInput.placeholder = 'Contoh: Kantor Pusat Jakarta';
            categoryLinkWrapper.style.display = '';
            categoryAttachmentWrapper.style.display = 'none';
        } else {
            // image | document
            categoryMessageWrapper.style.display = 'none';
            categoryLinkWrapper.style.display = 'none';
            categoryAttachmentWrapper.style.display = '';
            categoryAttachmentInput.setAttribute('accept', CATEGORY_ATTACHMENT_ACCEPT[category] || '');
            categoryAttachmentHint.textContent = CATEGORY_ATTACHMENT_HINT[category] || '';
        }
    }

    categorySelect.addEventListener('change', syncManualCategory);
    syncManualCategory();

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
    } else if (getType() === 'drip') {
        addStepRow();
    }

    // Now that stepsContainer/addStepRow/existingSteps are all set up,
    // run the Jenis Pengiriman sync once for whatever type is selected
    // on load ($currentType above — the schedule's own type when
    // editing, 'recurring' by default on create).
    syncTypeSections();

    // --- Tab badge counters ---
    var phoneInput = document.getElementById('phoneNumbersInput');
    var countPhone = document.getElementById('countPhone');
    var countGroup = document.getElementById('countGroup');
    var countUser = document.getElementById('countUser');
    var countPhonebook = document.getElementById('countPhonebook');

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
    function updatePhonebookCount() {
        countPhonebook.textContent = document.querySelectorAll('#phonebookChecklist input.phonebook-checkbox:checked').length;
    }

    // Generic live-search: toggles each item's visibility based on
    // whether its data-search attribute contains the typed term, and
    // shows/hides a shared "nothing found" message. Reused for the Grup
    // WhatsApp, User Company, and Grup Buku Telepon tabs below instead of
    // three near-identical filter functions.
    function applySearchFilter(items, term, extraMatches, emptyEl) {
        term = (term || '').trim().toLowerCase();
        var anyVisible = false;

        items.forEach(function (item) {
            var matchesSearch = !term || (item.getAttribute('data-search') || '').indexOf(term) !== -1;
            var matchesExtra = !extraMatches || extraMatches(item);
            var visible = matchesSearch && matchesExtra;
            item.style.display = visible ? '' : 'none';
            if (visible) anyVisible = true;
        });

        if (emptyEl) emptyEl.classList.toggle('d-none', anyVisible || items.length === 0);
    }

    phoneInput.addEventListener('input', updatePhoneCount);
    updatePhoneCount();
    updateUserCount();
    updatePhonebookCount();

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
                    var groupLabel = group.name || group.chat_jid;

                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    wrap.setAttribute('data-search', groupLabel.toLowerCase());

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
                    label.textContent = groupLabel;

                    wrap.appendChild(input);
                    wrap.appendChild(label);
                    groupChecklist.appendChild(wrap);
                });
                updateGroupCount();
                // Re-apply whatever search term is already typed — groups
                // just got (re)loaded for a newly selected device, so the
                // previous DOM nodes (and their visibility) are gone.
                filterGroups();
            })
            .catch(function () {
                groupChecklist.innerHTML = '<p class="text-danger small mb-0">Gagal memuat grup WhatsApp.</p>';
                updateGroupCount();
            });
    }

    deviceSelect.addEventListener('change', function () { loadGroupsFor(deviceSelect.value); });
    var initialDeviceId = deviceSelect.getAttribute('data-selected');
    if (initialDeviceId) loadGroupsFor(initialDeviceId);

    // --- Tab 2 search: Grup WhatsApp ---
    var groupSearchInput = document.getElementById('groupSearchInput');
    var groupSearchEmpty = document.getElementById('groupSearchEmpty');

    function filterGroups() {
        var items = Array.prototype.slice.call(groupChecklist.querySelectorAll('.form-check'));
        applySearchFilter(items, groupSearchInput.value, null, groupSearchEmpty);
    }

    groupSearchInput.addEventListener('input', filterGroups);

    // --- Tab 3: Company Users — branch -> unit filter + search + select all ---
    var branchFilter = document.getElementById('userBranchFilter');
    var unitFilter = document.getElementById('userUnitFilter');
    var userSearchInput = document.getElementById('userSearchInput');
    var userSearchEmpty = document.getElementById('userSearchEmpty');
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

        applySearchFilter(userItems, userSearchInput.value, function (item) {
            var matchesBranch = !branchId || item.getAttribute('data-branch-office') === branchId;
            var matchesUnit = !unitId || item.getAttribute('data-branch-office-unit') === unitId;
            return matchesBranch && matchesUnit;
        }, userSearchEmpty);

        selectAll.checked = false;
    }

    branchFilter.addEventListener('change', function () { filterUnitsByBranch(); applyUserFilter(); });
    unitFilter.addEventListener('change', applyUserFilter);
    userSearchInput.addEventListener('input', applyUserFilter);

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

    // --- Tab 4: Grup Buku Telepon — kelompok filter + search + select all ---
    var phonebookCategoryFilter = document.getElementById('phonebookCategoryFilter');
    var phonebookSearchInput = document.getElementById('phonebookSearchInput');
    var phonebookSearchEmpty = document.getElementById('phonebookSearchEmpty');
    var phonebookSelectAll = document.getElementById('phonebookSelectAll');
    var phonebookItems = Array.prototype.slice.call(document.querySelectorAll('.phonebook-checklist-item'));

    function applyPhonebookFilter() {
        var categoryId = phonebookCategoryFilter.value;

        applySearchFilter(phonebookItems, phonebookSearchInput.value, function (item) {
            return !categoryId || item.getAttribute('data-category') === categoryId;
        }, phonebookSearchEmpty);

        phonebookSelectAll.checked = false;
    }

    phonebookCategoryFilter.addEventListener('change', applyPhonebookFilter);
    phonebookSearchInput.addEventListener('input', applyPhonebookFilter);

    phonebookSelectAll.addEventListener('change', function () {
        phonebookItems.forEach(function (item) {
            if (item.style.display === 'none') return;
            var checkbox = item.querySelector('.phonebook-checkbox');
            if (checkbox && !checkbox.disabled) checkbox.checked = phonebookSelectAll.checked;
        });
        updatePhonebookCount();
    });

    document.querySelectorAll('.phonebook-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updatePhonebookCount);
    });
})();
</script>
