@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('form.branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.category.index', ['branch_office_id' => $header->branch_office_id]) }}">Form Category</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.header.index', $header->form_category_id) }}">{{ $header->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">Pengaturan Form — {{ $header->name }}</h4>
                    <p class="text-muted mb-0">Atur apa yang terjadi saat ada orang berhasil submit form ini.</p>
                </div>

                @if(! $waActive)
                    <div class="alert alert-warning mb-0">
                        <strong>Layanan Chat/WhatsApp belum aktif.</strong>
                        Notifikasi WA saat submit form cuma bisa diaktifkan kalau company Anda punya package aktif kategori
                        <em>Chat</em>, <em>WhatsApp</em>, atau <em>Whatsapp Blast</em>. Redeem voucher / beli package kategori
                        tersebut dulu, lalu buka kembali halaman ini.
                    </div>
                @else
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('form.setting.update', $header->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="notify_wa_enabled" value="1" id="notifyWaEnabled" class="form-check-input"
                                @checked(old('notify_wa_enabled', $setting->notify_wa_enabled ?? false))>
                            <label class="form-check-label" for="notifyWaEnabled">Aktifkan notifikasi WA saat submit berhasil</label>
                        </div>
                        <div class="form-text mb-3">
                            Notifikasi dikirim ke <strong>admin/staff branch</strong> yang menaungi form ini (bukan ke nomor pengisi
                            form) — sesuai daftar anggota yang bisa mengelola branch tersebut.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Device Pengirim</label>
                            <select name="device_id" class="form-select @error('device_id') is-invalid @enderror">
                                <option value="">- Pilih Device -</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" @selected(old('device_id', $setting->device_id ?? '') == $device->id)>
                                        {{ $device->phone_number ?? $device->id }} ({{ $device->status }})
                                    </option>
                                @endforeach
                            </select>
                            @error('device_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if ($devices->isEmpty())
                                <div class="form-text text-danger">Belum ada device WA terhubung. Sambungkan device dulu di menu Chat &gt; Connect Device.</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Template Pesan WA</label>
                            <select name="wa_message_template_id" class="form-select @error('wa_message_template_id') is-invalid @enderror">
                                <option value="">- Tanpa Template (notifikasi tidak terkirim) -</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('wa_message_template_id', $setting->wa_message_template_id ?? '') == $template->id)>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('wa_message_template_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Hanya template yang sudah <em>approved</em> WA Blast yang muncul di sini. Tag yang tersedia:
                                <code>@{{nama_form}}</code>, <code>@{{waktu_submit}}</code>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Info Tambahan (opsional)</label>
                            <textarea name="additional_info" rows="3" class="form-control @error('additional_info') is-invalid @enderror"
                                placeholder="Misal: link Zoom, alamat lokasi, atau info lain untuk peserta...">{{ old('additional_info', $setting->additional_info ?? '') }}</textarea>
                            @error('additional_info')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Dipakai untuk catatan internal (mis. link Zoom) — tidak ditampilkan otomatis di form publik.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status Pengaturan</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="active" @selected(old('status', $setting->status ?? 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $setting->status ?? 'active') === 'inactive')>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
