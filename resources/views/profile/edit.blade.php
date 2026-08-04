@extends('layouts.dashboard')

@section('content')
    <div class="mx-auto" style="max-width: 640px;">
        <div class="mb-4">
            <h4 class="mb-1">Profil Saya</h4>
            <p class="text-muted mb-0">Kelola foto, nama, password, dan PIN transaksi Anda.</p>
        </div>

        @if (session('status') === 'profile-updated')
            <div class="alert alert-success">Profil berhasil diperbarui.</div>
        @endif
        @if (session('status') === 'password-updated')
            <div class="alert alert-success">Password berhasil diperbarui.</div>
        @endif

        {{-- Foto & Nama --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="mb-3">Info Profil</h6>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img id="avatar-preview" src="{{ $user->avatarUrl() }}" alt="Avatar"
                            class="rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                        <div>
                            <label for="image" class="btn btn-outline-secondary btn-sm mb-1">
                                <i class="ri-camera-line"></i> Ubah Foto
                            </label>
                            <input type="file" name="image" id="image" accept="image/png,image/jpeg,image/webp" class="d-none">
                            <div class="text-muted fs-12">JPG, PNG, atau WEBP. Maks 2MB.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" class="form-control" value="{{ $user->email }}" disabled readonly>
                        <div class="form-text">Email tidak dapat diubah.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>
        </div>

        {{-- Ubah Password --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="mb-3">Ubah Password</h6>

                @if ($errors->updatePassword->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->updatePassword->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" id="current_password" class="form-control" autocomplete="current-password" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" required>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Ulangi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
                </form>
            </div>
        </div>

        {{-- PIN Transaksi --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1">PIN Transaksi</h6>
                    <p class="text-muted mb-0 fs-14">
                        {{ $user->pin ? 'PIN sudah dibuat, dipakai untuk konfirmasi transfer saldo.' : 'Belum ada PIN — wajib dibuat sebelum bisa transfer saldo.' }}
                    </p>
                </div>
                <a href="{{ route('user-settings.pin.edit') }}" class="btn btn-outline-primary">
                    <i class="ri-shield-keyhole-line"></i> {{ $user->pin ? 'Ubah PIN' : 'Buat PIN' }}
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('image');
        var preview = document.getElementById('avatar-preview');

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                preview.src = URL.createObjectURL(input.files[0]);
            }
        });
    });
    </script>
@endsection
