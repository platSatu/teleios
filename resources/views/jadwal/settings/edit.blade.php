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

        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">Pengaturan Pengingat Jadwal</h4>
                    <p class="text-muted mb-0">Kirim pengingat WhatsApp otomatis ke orang tua/murid sebelum jadwal kelas dimulai.</p>
                </div>

                @if(! $chatActive)
                    <div class="alert alert-warning mb-0">
                        <strong>Layanan Chat/WhatsApp belum aktif.</strong>
                        Pengingat WA untuk Jadwal cuma bisa diaktifkan kalau company Anda punya package aktif kategori
                        <em>Chat</em> atau <em>WhatsApp</em>. Redeem voucher / beli package kategori tersebut dulu,
                        lalu buka kembali halaman ini.
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

                    <form action="{{ route('jadwal.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="enabled" value="1" id="reminderEnabled" class="form-check-input"
                                @checked(old('enabled', $setting->enabled ?? false))>
                            <label class="form-check-label" for="reminderEnabled">Aktifkan pengingat WA untuk Jadwal Kelas</label>
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
                            <label class="form-label">Template Pesan (opsional)</label>
                            <select name="wa_message_template_id" class="form-select @error('wa_message_template_id') is-invalid @enderror">
                                <option value="">- Tanpa Template (pakai pesan default) -</option>
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
                                Tag yang tersedia di pesan: <code>@{{nama_murid}}</code>, <code>@{{nama_pengajar}}</code>,
                                <code>@{{mata_pelajaran}}</code>, <code>@{{tanggal}}</code>, <code>@{{jam_mulai}}</code>,
                                <code>@{{jam_selesai}}</code>, <code>@{{nama_perusahaan}}</code>.
                                Lampiran pada template (kalau ada) tidak ikut terkirim untuk pengingat Jadwal, hanya teksnya.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kirim Berapa Lama Sebelumnya</label>
                                <input type="number" name="remind_value" min="1" max="720" class="form-control @error('remind_value') is-invalid @enderror"
                                    value="{{ old('remind_value', $setting->remind_value ?? 1) }}">
                                @error('remind_value')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Satuan</label>
                                <select name="remind_unit" class="form-select @error('remind_unit') is-invalid @enderror">
                                    <option value="hours" @selected(old('remind_unit', $setting->remind_unit ?? 'days') === 'hours')>Jam</option>
                                    <option value="days" @selected(old('remind_unit', $setting->remind_unit ?? 'days') === 'days')>Hari</option>
                                </select>
                                @error('remind_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kirim Ke</label>
                                <select name="remind_target" class="form-select @error('remind_target') is-invalid @enderror">
                                    <option value="parent" @selected(old('remind_target', $setting->remind_target ?? 'parent') === 'parent')>Orang Tua</option>
                                    <option value="student" @selected(old('remind_target', $setting->remind_target ?? 'parent') === 'student')>Murid</option>
                                    <option value="both" @selected(old('remind_target', $setting->remind_target ?? 'parent') === 'both')>Orang Tua &amp; Murid</option>
                                </select>
                                @error('remind_target')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="form-text mb-3">
                            Nomor HP diambil dari data Student masing-masing (menu Student). Kalau nomor yang dipilih kosong untuk
                            Student tertentu, pengingat untuk Student itu otomatis dilewati.
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <h5 class="mb-1">Reminder ke Pengajar (Jadwal v2)</h5>
                            <p class="text-muted mb-0">
                                Rekap jadwal mengajar H-1 (otomatis tiap sore) dan lewat request manual pengajar via WA. Pakai
                                device pengirim yang sama dengan pengaturan di atas -- tidak ada device/nomor terpisah.
                            </p>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="remind_notify_pengajar" value="1" id="remindNotifyPengajar" class="form-check-input"
                                @checked(old('remind_notify_pengajar', $setting->remind_notify_pengajar ?? false))>
                            <label class="form-check-label" for="remindNotifyPengajar">Kirim rekap H-1 otomatis ke pengajar tiap sore</label>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Template Pesan Rekap Pengajar (opsional)</label>
                                <select name="wa_message_template_id_pengajar" class="form-select @error('wa_message_template_id_pengajar') is-invalid @enderror">
                                    <option value="">- Tanpa Template (pakai pesan default) -</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('wa_message_template_id_pengajar', $setting->wa_message_template_id_pengajar ?? '') == $template->id)>
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('wa_message_template_id_pengajar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Tag: <code>@{{nama_pengajar}}</code>, <code>@{{tanggal}}</code> / <code>@{{rentang_tanggal}}</code>,
                                    <code>@{{jumlah_sesi}}</code>, <code>@{{daftar_sesi}}</code>, <code>@{{nama_perusahaan}}</code>.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kata Kunci Request Jadwal via WA</label>
                                <input type="text" name="pengajar_request_keyword" class="form-control @error('pengajar_request_keyword') is-invalid @enderror"
                                    value="{{ old('pengajar_request_keyword', $setting->pengajar_request_keyword ?? 'jadwal') }}" maxlength="50">
                                @error('pengajar_request_keyword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Kata yang diketik pengajar di WA (persis, tanpa embel-embel lain) untuk minta rekap jadwal minggu ini kapan saja.
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <h5 class="mb-1">Notifikasi Perubahan Jadwal</h5>
                            <p class="text-muted mb-0">
                                Saat staff menyetujui/menolak permintaan ubah jadwal (menu Reschedule Requests), ATAU saat admin
                                mengubah jam/pengajar langsung lewat form Edit Jadwal Kelas, pilih siapa saja yang otomatis dapat
                                notifikasi WA-nya. Bisa dicentang lebih dari satu sekaligus.
                            </p>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="reschedule_notify_requester" value="1" id="rescheduleNotifyRequester" class="form-check-input"
                                    @checked(old('reschedule_notify_requester', $setting->reschedule_notify_requester ?? true))>
                                <label class="form-check-label" for="rescheduleNotifyRequester">Orang tua/murid yang minta reschedule</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="reschedule_notify_pengajar" value="1" id="rescheduleNotifyPengajar" class="form-check-input"
                                    @checked(old('reschedule_notify_pengajar', $setting->reschedule_notify_pengajar ?? false))>
                                <label class="form-check-label" for="rescheduleNotifyPengajar">Pengajar yang bersangkutan (termasuk saat admin edit jadwal langsung)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="reschedule_notify_admin" value="1" id="rescheduleNotifyAdmin" class="form-check-input"
                                    @checked(old('reschedule_notify_admin', $setting->reschedule_notify_admin ?? false))>
                                <label class="form-check-label" for="rescheduleNotifyAdmin">Admin/pemilik company</label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Template Pesan — Disetujui (opsional)</label>
                                <select name="wa_message_template_id_reschedule_approved" class="form-select @error('wa_message_template_id_reschedule_approved') is-invalid @enderror">
                                    <option value="">- Tanpa Template (pakai pesan default) -</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('wa_message_template_id_reschedule_approved', $setting->wa_message_template_id_reschedule_approved ?? '') == $template->id)>
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('wa_message_template_id_reschedule_approved')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Template Pesan — Ditolak (opsional)</label>
                                <select name="wa_message_template_id_reschedule_rejected" class="form-select @error('wa_message_template_id_reschedule_rejected') is-invalid @enderror">
                                    <option value="">- Tanpa Template (pakai pesan default) -</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('wa_message_template_id_reschedule_rejected', $setting->wa_message_template_id_reschedule_rejected ?? '') == $template->id)>
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('wa_message_template_id_reschedule_rejected')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="form-text mb-3">
                            Tag yang tersedia di kedua template: <code>@{{nama_murid}}</code>, <code>@{{nama_pengajar}}</code>,
                            <code>@{{mata_pelajaran}}</code>, <code>@{{catatan_staff}}</code>, <code>@{{nama_perusahaan}}</code>.
                            Isi pesan yang sama dikirim ke semua penerima yang dicentang di atas. Lampiran pada template (kalau ada)
                            tidak ikut terkirim, hanya teksnya.
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
