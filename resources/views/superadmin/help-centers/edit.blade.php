@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Tiket — {{ $helpCenter->number_ticket }}</h4>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('help-center.update', $helpCenter->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="category_help_centers_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select name="category_help_centers_id" id="category_help_centers_id" class="form-select" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_help_centers_id', $helpCenter->category_help_centers_id) == $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Judul <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $helpCenter->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" class="form-control" rows="4" required>{{ old('description', $helpCenter->description) }}</textarea>
                        </div>

                        <div class="mb-3">
                            @if ($helpCenter->attachment)
                                <label class="form-label d-block">Lampiran saat ini</label>
                                <a href="{{ asset('storage/' . $helpCenter->attachment) }}" target="_blank" class="btn btn-sm btn-outline-secondary mb-2">
                                    <i class="ri-download-line"></i> Lihat Lampiran
                                </a>
                            @endif
                            <label for="attachment" class="form-label d-block">Ganti Lampiran (PDF/JPG, opsional)</label>
                            <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.jpg,.jpeg">
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="open" @selected(old('status', $helpCenter->status) === 'open')>Open</option>
                                <option value="close" @selected(old('status', $helpCenter->status) === 'close')>Close</option>
                                <option value="active" @selected(old('status', $helpCenter->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $helpCenter->status) === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('help-center.show', $helpCenter->id) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
