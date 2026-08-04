@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Edit Company Role Menu</h4>
                    <p class="text-muted mb-4">{{ $companyRoleMenu->company->name ?? '-' }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('company-role-menu.update', $companyRoleMenu->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="category_application_id" class="form-label">Category Application <span class="text-danger">*</span></label>
                            <select name="category_application_id" id="category_application_id" class="form-select" required>
                                @foreach ($categoryApplications as $category)
                                    <option value="{{ $category->id }}"
                                        @selected(old('category_application_id', $companyRoleMenu->category_application_id) == $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="application_menu_id" class="form-label">Menu <span class="text-danger">*</span></label>
                            <select name="application_menu_id" id="application_menu_id" class="form-select" required>
                                @foreach ($applicationMenus as $menu)
                                    <option value="{{ $menu->id }}" data-category="{{ $menu->category_application_id }}"
                                        @selected(old('application_menu_id', $companyRoleMenu->application_menu_id) == $menu->id)>
                                        {{ $menu->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Daftar menu otomatis mengikuti Category Application yang dipilih.</div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" @selected(old('status', $companyRoleMenu->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $companyRoleMenu->status) === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('company-role-menu.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var categorySelect = document.getElementById('category_application_id');
            var menuSelect = document.getElementById('application_menu_id');
            var allMenuOptions = Array.prototype.slice.call(menuSelect.querySelectorAll('option[data-category]'));

            function filterMenus() {
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
            }

            categorySelect.addEventListener('change', filterMenus);
            filterMenus();
        });
    </script>
@endsection
