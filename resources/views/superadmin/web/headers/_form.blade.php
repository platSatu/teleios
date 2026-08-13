@csrf
@if (isset($header))
    @method('PUT')
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $backgroundType = old('background_type', $header->background_type ?? 'image');
@endphp

<div class="alert alert-info">
    Pilih salah satu: Video atau Gambar sebagai background slide ini. Keduanya tidak bisa aktif bersamaan — field yang tidak dipilih akan diabaikan di frontend.
</div>

{{--
    Form dibagi 2 kolom (kiri = media/background, kanan = konten &
    pengaturan) supaya tidak terlalu panjang ke bawah — lihat
    create.blade.php/edit.blade.php yang sekarang pakai col-lg-12
    (lebar penuh) supaya dua kolom ini muat berdampingan dengan nyaman.
--}}
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Media &amp; Background</h6>

                <div class="mb-3">
                    <label class="form-label">Tipe Background <span class="text-danger">*</span></label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input type="radio" name="background_type" value="image" id="background_type_image" class="form-check-input background-type-radio"
                                @checked($backgroundType === 'image')>
                            <label for="background_type_image" class="form-check-label">Gambar</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="background_type" value="video" id="background_type_video" class="form-check-input background-type-radio"
                                @checked($backgroundType === 'video')>
                            <label for="background_type_video" class="form-check-label">Video</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3" id="background_images_field">
                    <label for="background_images" class="form-label">Upload Gambar Background</label>
                    @if (! empty($header) && $header->background_images)
                        <div class="mb-2">
                            <img src="{{ $header->background_images_url }}" alt="Background" style="max-width: 240px;" class="rounded border">
                        </div>
                    @endif
                    <input type="file" name="background_images" id="background_images" class="form-control" accept="image/*">
                    <div class="form-text">Dipakai jika Tipe Background = Gambar. Maks 4MB.</div>
                </div>

                <div class="mb-3" id="thumbnail_background_images_field">
                    <label for="thumbnail_background_images" class="form-label">Thumbnail Gambar (opsional)</label>
                    @if (! empty($header) && $header->thumbnail_background_images)
                        <div class="mb-2">
                            <img src="{{ $header->thumbnail_background_images_url }}" alt="Thumbnail Gambar" style="max-width: 180px;" class="rounded border">
                        </div>
                    @endif
                    <input type="file" name="thumbnail_background_images" id="thumbnail_background_images" class="form-control" accept="image/*">
                    <div class="form-text">Opsional — turunan/preview ringan yang tampil duluan sebelum gambar background penuh selesai dimuat di frontend. Hanya relevan jika Tipe Background = Gambar.</div>
                </div>

                <div class="mb-3" id="videos_field">
                    <label for="videos" class="form-label">Upload File Video</label>
                    @if (! empty($header) && $header->videos)
                        <div class="mb-2">
                            <video src="{{ $header->videos_url }}" controls style="max-width: 240px;" class="rounded border"></video>
                        </div>
                    @endif
                    <input type="file" name="videos" id="videos" class="form-control" accept="video/*">
                    <div class="form-text">Dipakai jika Tipe Background = Video. Format mp4/mov/avi/wmv/mkv/webm, maks 100MB.</div>
                </div>

                <div class="mb-0" id="thumbnail_images_field">
                    <label for="thumbnail_images" class="form-label">Thumbnail Video (poster)</label>
                    @if (! empty($header) && $header->thumbnail_images)
                        <div class="mb-2">
                            <img src="{{ $header->thumbnail_images_url }}" alt="Thumbnail" style="max-width: 180px;" class="rounded border">
                        </div>
                    @endif
                    <input type="file" name="thumbnail_images" id="thumbnail_images" class="form-control" accept="image/*">
                    <div class="form-text">Opsional — gambar yang tampil sebelum/selagi video dimuat. Hanya relevan jika Tipe Background = Video.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Konten &amp; Pengaturan</h6>

                <div class="mb-3">
                    <label for="text" class="form-label">Headline</label>
                    <div class="input-group">
                        <input type="text" name="text" id="text" class="form-control" value="{{ old('text', $header->text ?? '') }}">
                        <input type="color" name="color_headline" class="form-control form-control-color" title="Warna headline"
                            value="{{ old('color_headline', $header->color_headline ?? '#ffffff') }}" style="max-width: 3rem;">
                    </div>
                    <div class="form-text">Kotak warna di samping mengatur warna teks headline saat ditampilkan di frontend.</div>
                </div>

                <div class="mb-3">
                    <label for="descriptions" class="form-label">Deskripsi</label>
                    <textarea name="descriptions" id="descriptions" class="form-control" rows="3">{{ old('descriptions', $header->descriptions ?? '') }}</textarea>
                    <div class="input-group mt-2">
                        <span class="input-group-text">Warna Deskripsi</span>
                        <input type="color" name="color_description" class="form-control form-control-color" title="Warna deskripsi"
                            value="{{ old('color_description', $header->color_description ?? '#ffffff') }}" style="max-width: 3rem;">
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="text-muted mb-3">Tombol CTA</h6>

                <div class="mb-3">
                    <label for="button_action" class="form-label">Tampilkan Tombol <span class="text-danger">*</span></label>
                    <select name="button_action" id="button_action" class="form-select" required>
                        <option value="active" @selected(old('button_action', $header->button_action ?? 'inactive') === 'active')>Active</option>
                        <option value="inactive" @selected(old('button_action', $header->button_action ?? 'inactive') === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="button_text" class="form-label">Teks Tombol</label>
                    <input type="text" name="button_text" id="button_text" class="form-control" value="{{ old('button_text', $header->button_text ?? '') }}" placeholder="mis. Daftar Sekarang">
                    <div class="form-text">Wajib diisi jika Tampilkan Tombol = Active.</div>
                </div>

                <div class="mb-3">
                    <label for="button_link" class="form-label">Link Tombol</label>
                    <input type="text" name="button_link" id="button_link" class="form-control" value="{{ old('button_link', $header->button_link ?? '') }}" placeholder="https://... atau /pricing">
                    <div class="form-text">Wajib diisi jika Tampilkan Tombol = Active.</div>
                </div>

                <hr class="my-3">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="sort_order" class="form-label">Urutan Tampil</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', $header->sort_order ?? 0) }}" min="0">
                        <div class="form-text">Angka kecil tampil lebih dulu.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" @selected(old('status', $header->status ?? 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $header->status ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mb-0">
                    <label for="user_id" class="form-label">User (opsional)</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">— Tidak terikat user —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id', $header->user_id ?? '') == $user->id)>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.headers.index') }}" class="btn btn-light">Batal</a>
</div>

<script>
    (function () {
        var imageField = document.getElementById('background_images_field');
        var thumbImageField = document.getElementById('thumbnail_background_images_field');
        var videoField = document.getElementById('videos_field');
        var thumbField = document.getElementById('thumbnail_images_field');
        var radios = document.querySelectorAll('.background-type-radio');

        function toggleFields() {
            var selected = document.querySelector('.background-type-radio:checked');
            var type = selected ? selected.value : 'image';

            imageField.style.display = type === 'image' ? '' : 'none';
            thumbImageField.style.display = type === 'image' ? '' : 'none';
            videoField.style.display = type === 'video' ? '' : 'none';
            thumbField.style.display = type === 'video' ? '' : 'none';
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', toggleFields);
        });

        toggleFields();
    })();
</script>
