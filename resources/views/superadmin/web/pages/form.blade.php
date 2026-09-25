@extends('layouts.dashboard')

@section('content')
    @php
        $isEdit = $page->exists;
        $type = old('type', $page->type);
    @endphp

    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('web.pages.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali"><i class="ri-arrow-left-line"></i></a>
                <h4 class="mb-0">{{ $isEdit ? 'Edit Halaman: '.$page->title : 'Tambah Halaman' }}</h4>
            </div>

            @include('components.notifikasi')

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

                    <form action="{{ $isEdit ? route('web.pages.update', $page->id) : route('web.pages.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control" maxlength="255" required value="{{ old('title', $page->title) }}" placeholder="mis. Kebijakan Privasi">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="type" class="form-label">Tipe <span class="text-danger">*</span></label>
                                <select name="type" id="type" class="form-select" @disabled($isEdit)>
                                    @foreach (\App\Models\WebPage::TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($isEdit)
                                    <div class="form-text">Tipe tidak bisa diubah setelah dibuat.</div>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Alamat halaman</label>
                            <div class="input-group">
                                <span class="input-group-text">/page/</span>
                                <input type="text" name="slug" id="slug" class="form-control" maxlength="191" value="{{ old('slug', $page->slug) }}" placeholder="otomatis dari judul, mis. kebijakan-privasi">
                            </div>
                            <div class="form-text">Huruf kecil, angka, dan "-". Hati-hati mengganti alamat halaman yang sudah dibagikan: link lama akan tidak ditemukan.</div>
                        </div>

                        <div class="mb-3">
                            <label for="subtitle" class="form-label">Subjudul</label>
                            <textarea name="subtitle" id="subtitle" class="form-control" rows="2" maxlength="1000">{{ old('subtitle', $page->subtitle) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="hero_image" class="form-label">Gambar latar header (opsional)</label>
                            @if ($page->hero_image)
                                <div class="mb-2"><img src="{{ $page->hero_image_url }}" alt="" style="max-width: 260px;" class="rounded border"></div>
                            @endif
                            <input type="file" name="hero_image" id="hero_image" class="form-control" accept="image/*">
                            <div class="form-text">Tampil di bawah menu, di belakang judul. Kosongkan untuk header polos.</div>
                        </div>

                        @if ($type === 'document')
                            <div class="mb-3">
                                <label for="content" class="form-label">Isi dokumen</label>
                                <textarea name="content" id="content" class="form-control font-monospace" rows="18" maxlength="100000">{{ old('content', $page->content) }}</textarea>
                                <div class="form-text">
                                    Format: <code>## Judul bab</code> · <code>### Sub-bab</code> · <code>**tebal**</code> · <code>- poin</code> · <code>1. nomor</code> · <code>[teks](https://link)</code>.
                                    Pisahkan paragraf dengan baris kosong. Daftar isi dibuat otomatis dari judul bab. HTML tidak diizinkan.
                                </div>
                            </div>
                        @elseif (! $isEdit)
                            <div class="alert alert-info py-2">Section halaman landing ditambahkan setelah halaman disimpan.</div>
                        @endif

                        <hr>
                        <h6 class="text-muted mb-3">Tampil di</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" name="show_in_navbar" value="1" id="show_in_navbar" class="form-check-input" @checked(old('show_in_navbar', $page->show_in_navbar))>
                                    <label for="show_in_navbar" class="form-check-label">Menu atas (navbar)</label>
                                </div>
                                <input type="number" name="navbar_order" class="form-control form-control-sm" min="0" max="999" value="{{ old('navbar_order', $page->navbar_order ?? 0) }}" placeholder="Urutan">
                                <div class="form-text">Urutan: angka kecil tampil lebih dulu (setelah Home).</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" name="show_in_footer" value="1" id="show_in_footer" class="form-check-input" @checked(old('show_in_footer', $page->show_in_footer))>
                                    <label for="show_in_footer" class="form-check-label">Footer</label>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="footer_group" class="form-control" maxlength="100" value="{{ old('footer_group', $page->footer_group) }}" placeholder="Nama kolom, mis. Perusahaan">
                                    <input type="number" name="footer_order" class="form-control" min="0" max="999" style="max-width: 90px;" value="{{ old('footer_order', $page->footer_order ?? 0) }}" title="Urutan">
                                </div>
                                <div class="form-text">Nama kolom sama dengan grup di Web → Footer = digabung di kolom itu.</div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3">SEO & share link</h6>
                        <div class="mb-3">
                            <label for="meta_description" class="form-label">Meta description</label>
                            <textarea name="meta_description" id="meta_description" class="form-control" rows="2" maxlength="500">{{ old('meta_description', $page->meta_description) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label for="meta_image" class="form-label">Gambar share (opsional)</label>
                            @if ($page->meta_image)
                                <div class="mb-2"><img src="{{ \App\Helpers\WebImageUploader::url($page->meta_image) }}" alt="" style="max-width: 200px;" class="rounded border"></div>
                            @endif
                            <input type="file" name="meta_image" id="meta_image" class="form-control" accept="image/*">
                            <div class="form-text">Kosong = pakai gambar header.</div>
                        </div>

                        <div class="mb-4" style="max-width: 260px;">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" @selected(old('status', $page->status) === 'active')>Tayang</option>
                                <option value="inactive" @selected(old('status', $page->status) === 'inactive')>Draft (tidak tampil)</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('web.pages.index') }}" class="btn btn-light">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($isEdit && $page->isLanding())
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-1">Section halaman</h5>
                        <p class="text-muted small mb-0">Sama seperti Susunan Beranda: urutkan dengan ↑↓, tambah section sesuai kebutuhan.</p>
                        @include('superadmin.web.home-sections._sections-table', ['sections' => $sections, 'pageId' => $page->id])
                    </div>
                </div>
            @endif
        </div>
    </div>

    @unless ($isEdit)
        <script>
            // Isi dokumen hanya untuk tipe Dokumen; tipe Landing diisi lewat section setelah disimpan.
            document.getElementById('type').addEventListener('change', function () {
                document.getElementById('content')?.closest('.mb-3').classList.toggle('d-none', this.value !== 'document');
            });
        </script>
    @endunless
@endsection
