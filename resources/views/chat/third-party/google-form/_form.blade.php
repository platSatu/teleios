@csrf

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Informasi Integrasi</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Nama Integrasi <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $integration->name ?? '') }}" placeholder="mis. Form Pendaftaran Event" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Label untuk membedakan integrasi ini dari Google Form lain yang mungkin kamu buat.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Device Pengirim <span class="text-danger">*</span></label>
            <select name="device_id" class="wa-device-select form-select @error('device_id') is-invalid @enderror"
                data-selected="{{ old('device_id', $integration->device_id ?? '') }}" required>
                <option value="">Memuat device...</option>
            </select>
            @error('device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Nomor WhatsApp yang mengirim balasan feedback ke pengisi form.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" @selected(old('status', $integration->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $integration->status ?? 'active') === 'inactive')>Inactive</option>
            </select>
            <div class="form-text">Nonaktifkan untuk sementara menghentikan balasan otomatis tanpa menghapus integrasinya.</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Data dari Google Form</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Google Apps Script mengirim seluruh jawaban form sebagai satu JSON, memakai judul pertanyaan sebagai
            key-nya &mdash; contoh: <code>{"Nama": "Budi Santoso", "Nomor HP": "081234567890"}</code>. Isi kolom di
            bawah dengan judul pertanyaan (persis) yang berisi nomor WhatsApp tujuan.
        </p>

        <div class="mb-1">
            <label class="form-label">Nama Field Nomor WhatsApp <span class="text-danger">*</span></label>
            <input type="text" name="target_number_field" class="form-control @error('target_number_field') is-invalid @enderror"
                value="{{ old('target_number_field', $integration->target_number_field ?? '') }}"
                placeholder="mis. Nomor HP" required>
            @error('target_number_field')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Dicocokkan tanpa memandang huruf besar/kecil atau spasi di awal-akhir.</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <h6 class="mb-0">Balasan Feedback</h6>
    </div>
    <div class="card-body">
        <div class="mb-1">
            <label class="form-label">WA Template</label>
            <select name="wa_message_template_id" class="form-select @error('wa_message_template_id') is-invalid @enderror">
                <option value="">-- Pilih WA Template --</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected(old('wa_message_template_id', $integration->wa_message_template_id ?? '') === $template->id)>
                        {{ $template->name }}
                    </option>
                @endforeach
            </select>
            @error('wa_message_template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if ($templates->isEmpty())
                <div class="form-text text-danger">
                    Belum ada WA Template yang aktif &amp; disetujui. Buat dulu di
                    <a href="{{ route('chat.message-templates.index') }}">WA Template</a>.
                </div>
            @else
                <div class="form-text">
                    Isi template dikirim apa adanya, kecuali placeholder <code>@{{nama_variabel}}</code> yang
                    namanya cocok dengan salah satu judul pertanyaan di form &mdash; itu otomatis diisi jawaban
                    pengisi form. Placeholder yang tidak cocok dibiarkan seperti aslinya.
                </div>
            @endif
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-0">
    <div class="card-body d-flex justify-content-end gap-2">
        <a href="{{ route('chat.third-party.google-form.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">
            {{ ($integration ?? null) ? 'Simpan Perubahan' : 'Simpan Integrasi' }}
        </button>
    </div>
</div>
