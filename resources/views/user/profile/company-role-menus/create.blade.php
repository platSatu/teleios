@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Add Application</h4>
                <p class="text-muted mb-0">Company, Branch, Unit/Divisi, dan Role sudah otomatis terisi dari halaman
                    sebelumnya.</p>
            </div>
            <a href="{{ route('profile.company-roles.show', $role->id) }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
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

                        <form action="{{ route('profile.company-role-menus.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="company_role_id" value="{{ $role->id }}">

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Company</label>
                                <input type="text" class="form-control" value="{{ $company->name }}" disabled readonly>
                            </div>

                            @if ($branchOffice)
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Branch</label>
                                    <input type="text" class="form-control" value="{{ $branchOffice->name }}" disabled readonly>
                                </div>
                            @endif

                            @if ($unit)
                                <div class="mb-3">
                                    <label class="form-label text-muted small mb-1">Unit/Divisi</label>
                                    <input type="text" class="form-control" value="{{ $unit->name }}" disabled readonly>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Role</label>
                                <input type="text" class="form-control" value="{{ $role->name }}" disabled readonly>
                            </div>

                            <div class="mb-3">
                                <label for="category_application_id" class="form-label">Category Application <span
                                        class="text-danger">*</span></label>
                                <select name="category_application_id" id="category_application_id"
                                    class="form-select @error('category_application_id') is-invalid @enderror" required>
                                    <option value="">-- Pilih Category Application --</option>
                                    @foreach ($categoryApplications as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('category_application_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_application_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="application_menu_id" class="form-label">Menu <span
                                        class="text-danger">*</span></label>
                                <select name="application_menu_id" id="application_menu_id"
                                    class="form-select @error('application_menu_id') is-invalid @enderror" required>
                                    <option value="">-- Pilih Category dulu --</option>
                                    @foreach ($applicationMenus as $menu)
                                        <option value="{{ $menu->id }}" data-category="{{ $menu->category_application_id }}"
                                            {{ old('application_menu_id') == $menu->id ? 'selected' : '' }}>
                                            {{ $menu->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('application_menu_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Daftar menu otomatis mengikuti Category Application yang dipilih.</div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Simpan Application</button>
                                <a href="{{ route('profile.company-roles.show', $role->id) }}" class="btn btn-light">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Inline (not @push) — layouts.dashboard has no scripts stack, same
         reason resources/views/user/profile/index.blade.php's own scripts
         sit directly inside @section('content') instead of a stack. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var categorySelect = document.getElementById('category_application_id');
            var menuSelect = document.getElementById('application_menu_id');
            if (!categorySelect || !menuSelect) return;

            var allMenuOptions = Array.prototype.slice.call(menuSelect.querySelectorAll('option[data-category]'));

            var filterMenus = function () {
                var categoryId = categorySelect.value;

                allMenuOptions.forEach(function (opt) {
                    var matches = opt.getAttribute('data-category') === categoryId;
                    opt.hidden = !matches;
                    opt.disabled = !matches;
                });

                var selected = menuSelect.querySelector('option:checked');
                if (selected && selected.hasAttribute('data-category') && selected.getAttribute('data-category') !== categoryId) {
                    menuSelect.value = '';
                }
            };

            categorySelect.addEventListener('change', filterMenus);
            filterMenus();
        });
    </script>
@endsection
