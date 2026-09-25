@extends('layouts.dashboard')

@section('content')
    @php
        $isEdit = $section->exists;
        $bgType = old('background_type', $section->background_type ?? 'none');
        $backUrl = $section->web_page_id ? route('web.pages.edit', $section->web_page_id) : route('web.home-sections.index');
    @endphp

    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ $backUrl }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali"><i class="ri-arrow-left-line"></i></a>
                <h4 class="mb-0">
                    {{ $isEdit ? 'Edit' : 'Tambah' }} Section: {{ $section->label() }}
                    <span class="text-muted fs-14 fw-normal">· {{ $section->web_page_id ? 'Halaman '.($section->page?->title ?? \App\Models\WebPage::find($section->web_page_id)?->title) : 'Beranda' }}</span>
                </h4>
            </div>

            @include('components.notifikasi')

            @if ($section->typeConfig('source'))
                <div class="alert alert-info py-2">
                    Isi section ini diambil otomatis dari menu <strong>{{ $section->typeConfig('source') }}</strong>.
                    @if ($section->hasFrame())
                        Di sini Anda mengatur bingkainya (judul, background, tombol).
                    @else
                        Di sini Anda hanya mengatur tampil/sembunyi; urutan diatur dari daftar Susunan Beranda.
                    @endif
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ $isEdit ? route('web.home-sections.update', $section->id) : route('web.home-sections.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @else
                            <input type="hidden" name="type" value="{{ $section->type }}">
                            @if ($section->web_page_id)
                                <input type="hidden" name="web_page_id" value="{{ $section->web_page_id }}">
                            @endif
                        @endif

                        @if ($section->hasFrame())
                            <h6 class="text-muted mb-3">Bingkai</h6>
                            <div class="mb-3">
                                <label for="title" class="form-label">Judul</label>
                                <input type="text" name="title" id="title" class="form-control" maxlength="255" value="{{ old('title', $section->title) }}" placeholder="mis. Kenapa Memilih Bizbos?">
                            </div>
                            <div class="mb-3">
                                <label for="subtitle" class="form-label">Subjudul</label>
                                <textarea name="subtitle" id="subtitle" class="form-control" rows="2" maxlength="1000">{{ old('subtitle', $section->subtitle) }}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="text_align" class="form-label">Posisi judul</label>
                                    <select name="text_align" id="text_align" class="form-select">
                                        <option value="center" @selected(old('text_align', $section->text_align) === 'center')>Tengah</option>
                                        <option value="left" @selected(old('text_align', $section->text_align) === 'left')>Kiri</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="background_type" class="form-label">Background</label>
                                    <select name="background_type" id="background_type" class="form-select">
                                        <option value="none" @selected($bgType === 'none')>Default (tanpa background)</option>
                                        <option value="color" @selected($bgType === 'color')>Warna</option>
                                        <option value="image" @selected($bgType === 'image')>Gambar</option>
                                        @if ($section->allowsVideo())
                                            <option value="video" @selected($bgType === 'video')>Video</option>
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3" data-bg="color">
                                <label for="background_color" class="form-label">Warna background</label>
                                <input type="color" name="background_color" id="background_color" class="form-control form-control-color" value="{{ old('background_color', $section->background_color ?: '#ffffff') }}">
                            </div>
                            <div class="mb-3" data-bg="image">
                                <label for="background_image" class="form-label">Gambar background</label>
                                @if ($section->background_image)
                                    <div class="mb-2"><img src="{{ $section->background_image_url }}" alt="" style="max-width: 240px;" class="rounded border"></div>
                                @endif
                                <input type="file" name="background_image" id="background_image" class="form-control" accept="image/*">
                                <div class="form-text">Maks 4MB. Teks otomatis diberi lapisan gelap supaya terbaca.</div>
                            </div>
                            @if ($section->allowsVideo())
                                <div class="mb-3" data-bg="video">
                                    <label for="background_video" class="form-label">Video background</label>
                                    @if ($section->background_video)
                                        <div class="mb-2 small"><a href="{{ $section->background_video_url }}" target="_blank" rel="noopener">Lihat video saat ini</a></div>
                                    @endif
                                    <input type="file" name="background_video" id="background_video" class="form-control" accept="video/mp4,video/webm">
                                    <div class="form-text">MP4/WebM, maks 50MB. Diputar otomatis tanpa suara.</div>
                                </div>
                            @endif

                            <div class="row">
                                @foreach (['cta' => 'Tombol 1', 'cta2' => 'Tombol 2'] as $prefix => $label)
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">{{ $label }} (opsional)</label>
                                        <input type="text" name="{{ $prefix }}_text" class="form-control mb-2" maxlength="60" placeholder="Teks, mis. Coba Gratis" value="{{ old($prefix.'_text', $section->{$prefix.'_text'}) }}">
                                        <input type="text" name="{{ $prefix }}_link" class="form-control" maxlength="500" placeholder="https://... atau /artikel" value="{{ old($prefix.'_link', $section->{$prefix.'_link'}) }}">
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($section->typeConfig('content'))
                            <hr>
                            <h6 class="text-muted mb-3">Isi</h6>
                            <div class="mb-3">
                                <label for="content" class="form-label">Teks</label>
                                <textarea name="content" id="content" class="form-control" rows="8" maxlength="10000">{{ old('content', $section->content) }}</textarea>
                                <div class="form-text">Pisahkan paragraf dengan baris kosong.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="media_image" class="form-label">Gambar samping</label>
                                    @if ($section->media_image)
                                        <div class="mb-2"><img src="{{ $section->media_image_url }}" alt="" style="max-width: 200px;" class="rounded border"></div>
                                    @endif
                                    <input type="file" name="media_image" id="media_image" class="form-control" accept="image/*">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="media_position" class="form-label">Posisi gambar</label>
                                    <select name="media_position" id="media_position" class="form-select">
                                        <option value="right" @selected(old('media_position', $section->media_position) === 'right')>Kanan</option>
                                        <option value="left" @selected(old('media_position', $section->media_position) === 'left')>Kiri</option>
                                    </select>
                                </div>
                            </div>
                        @endif

                        @if ($section->typeConfig('articles'))
                            <hr>
                            <h6 class="text-muted mb-3">Isi</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="item_limit" class="form-label">Jumlah artikel</label>
                                    <input type="number" name="item_limit" id="item_limit" class="form-control" min="1" max="12" value="{{ old('item_limit', $section->item_limit ?: 3) }}">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="web_category_article_id" class="form-label">Kategori</label>
                                    <select name="web_category_article_id" id="web_category_article_id" class="form-select">
                                        <option value="">Semua kategori</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(old('web_category_article_id', $section->web_category_article_id) === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-text mb-3">Artikel terbaru tampil otomatis, lengkap dengan tombol "Baca selengkapnya".</div>
                        @endif

                        <hr>
                        <div class="mb-4" style="max-width: 260px;">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" @selected(old('status', $section->status) === 'active')>Tampil</option>
                                <option value="inactive" @selected(old('status', $section->status) === 'inactive')>Disembunyikan</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($isEdit && $section->hasItems())
                @include('superadmin.web.home-sections._items')
            @elseif (! $isEdit && $section->hasItems())
                <p class="text-muted small">Item section bisa ditambahkan setelah section disimpan.</p>
            @endif
        </div>
    </div>

    @if ($section->hasFrame())
        <script>
            (function () {
                var select = document.getElementById('background_type');
                var toggle = function () {
                    document.querySelectorAll('[data-bg]').forEach(function (el) {
                        el.classList.toggle('d-none', el.dataset.bg !== select.value);
                    });
                };
                select.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    @endif
@endsection
