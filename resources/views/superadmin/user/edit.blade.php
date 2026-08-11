@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit User</h4>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('superadmin-users.update', $user->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="handphone" class="form-label">Handphone (WhatsApp)</label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" inputmode="numeric" name="handphone" id="handphone"
                                    class="form-control @error('handphone') is-invalid @enderror"
                                    value="{{ old('handphone', $user->handphone ? substr($user->handphone, 2) : '') }}"
                                    placeholder="81234567890" maxlength="14">
                                @error('handphone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">Tanpa awalan 0 atau kode negara 62 — cukup 10-14 digit setelahnya. Kosongkan untuk menghapus nomor.</div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="user_type" class="form-label">Tipe User <span class="text-danger">*</span></label>
                            <select name="user_type" id="user_type" class="form-select" required>
                                <option value="USER" @selected(old('user_type', $user->user_type) === 'USER')>USER</option>
                                <option value="SUPERADMIN" @selected(old('user_type', $user->user_type) === 'SUPERADMIN')>SUPERADMIN</option>
                            </select>
                            <small class="text-muted">Wallet, deposit, dan fitur lain milik user ini tidak diubah di sini.</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('superadmin-users.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
