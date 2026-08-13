@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Pengaturan Web</h4>
                    <p class="text-muted mb-4">
                        Pengaturan satu baris ini (favicon, logo, meta tags, kontak, GTM/GA, Google Maps) dipakai
                        publik di frontend fe-konexa lewat API — lihat App\Models\WebSetting.
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
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

                    <form action="{{ route('web.setting.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Favicon, Logo &amp; Meta Image</h6>

                                        <div class="mb-3">
                                            <label for="favicon" class="form-label">Favicon</label>
                                            @if ($setting->favicon)
                                                <div class="mb-2">
                                                    <img src="{{ $setting->favicon_url }}" alt="Favicon" style="max-width: 64px;" class="rounded border">
                                                </div>
                                            @endif
                                            <input type="file" name="favicon" id="favicon" class="form-control" accept="image/*">
                                            <div class="form-text">Idealnya persegi (mis. 512x512px). Maks 1MB.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Logo</label>
                                            @if ($setting->logo)
                                                <div class="mb-2">
                                                    <img src="{{ $setting->logo_url }}" alt="Logo" style="max-height: 60px;" class="rounded border">
                                                </div>
                                            @endif
                                            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                                            <div class="form-text">Ditampilkan di navbar frontend fe-konexa. Maks 2MB.</div>
                                        </div>

                                        <div class="mb-0">
                                            <label for="meta_images" class="form-label">Meta Image (og:image)</label>
                                            @if ($setting->meta_images)
                                                <div class="mb-2">
                                                    <img src="{{ $setting->meta_images_url }}" alt="Meta Image" style="max-width: 240px;" class="rounded border">
                                                </div>
                                            @endif
                                            <input type="file" name="meta_images" id="meta_images" class="form-control" accept="image/*">
                                            <div class="form-text">Gambar preview saat link website dibagikan (WhatsApp/Facebook/dll). Maks 4MB.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Meta Tags &amp; Kontak</h6>

                                        <div class="mb-3">
                                            <label for="meta_description" class="form-label">Meta Description</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" rows="2">{{ old('meta_description', $setting->meta_description) }}</textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                            <textarea name="meta_keywords" id="meta_keywords" class="form-control" rows="2" placeholder="Pisahkan dengan koma">{{ old('meta_keywords', $setting->meta_keywords) }}</textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="handphone" class="form-label">No. Handphone</label>
                                                <input type="text" name="handphone" id="handphone" class="form-control" value="{{ old('handphone', $setting->handphone) }}">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $setting->email) }}">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="address" class="form-label">Alamat</label>
                                            <textarea name="address" id="address" class="form-control" rows="2">{{ old('address', $setting->address) }}</textarea>
                                        </div>

                                        <hr class="my-3">
                                        <h6 class="text-muted mb-3">Tracking &amp; Maps</h6>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="google_tag" class="form-label">Google Tag Manager ID</label>
                                                <input type="text" name="google_tag" id="google_tag" class="form-control" value="{{ old('google_tag', $setting->google_tag) }}" placeholder="GTM-XXXXXXX">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="google_analytics" class="form-label">Google Analytics ID</label>
                                                <input type="text" name="google_analytics" id="google_analytics" class="form-control" value="{{ old('google_analytics', $setting->google_analytics) }}" placeholder="G-XXXXXXXXXX">
                                            </div>
                                        </div>

                                        <div class="mb-0">
                                            <label for="gmaps" class="form-label">Google Maps Embed URL</label>
                                            <textarea name="gmaps" id="gmaps" class="form-control" rows="2" placeholder="https://www.google.com/maps/embed?...">{{ old('gmaps', $setting->gmaps) }}</textarea>
                                            <div class="form-text">Ambil dari Google Maps &gt; Share &gt; Embed a map &gt; salin src iframe-nya.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
