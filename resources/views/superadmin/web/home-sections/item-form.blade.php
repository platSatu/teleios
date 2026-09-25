@extends('layouts.dashboard')

@section('content')
    @php
        $fields = $section->itemFields();
        $isEdit = $item->exists;
    @endphp
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <div class="row justify-content-center">
        <div class="col-xl-7">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('web.home-sections.edit', $section->id) }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali"><i class="ri-arrow-left-line"></i></a>
                <h4 class="mb-0">{{ $isEdit ? 'Edit' : 'Tambah' }} Item · {{ $section->title ?: $section->label() }}</h4>
            </div>

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

                    <form action="{{ $isEdit ? route('web.home-section-items.update', $item->id) : route('web.home-sections.items.store', $section->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        @foreach ($fields as $field => [$label, $required])
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                @switch($field)
                                    @case('image')
                                        @if ($item->image)
                                            <div class="mb-2"><img src="{{ $item->image_url }}" alt="" style="max-width: 160px;" class="rounded border"></div>
                                        @endif
                                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                                        <div class="form-text">Maks 4MB.</div>
                                        @break
                                    @case('icon')
                                        <div class="input-group">
                                            <span class="input-group-text"><i id="icon-preview" class="bi bi-{{ old('icon', $item->icon) ?: 'question-circle' }}"></i></span>
                                            <input type="text" name="icon" id="icon" class="form-control" maxlength="60" placeholder="mis. shield-check" value="{{ old('icon', $item->icon) }}">
                                        </div>
                                        <div class="form-text">
                                            Nama ikon dari <a href="https://icons.getbootstrap.com" target="_blank" rel="noopener">Bootstrap Icons</a>, tanpa awalan "bi-".
                                            Contoh: shield-check, robot, lightning-charge, chat-dots, headset, graph-up-arrow.
                                        </div>
                                        @break
                                    @case('description')
                                        <textarea name="description" id="description" class="form-control" rows="4" maxlength="2000">{{ old('description', $item->description) }}</textarea>
                                        @break
                                    @default
                                        <input type="text" name="{{ $field }}" id="{{ $field }}" class="form-control"
                                            maxlength="{{ $field === 'link_url' ? 500 : ($field === 'link_text' ? 60 : 255) }}"
                                            @if ($field === 'link_url') placeholder="https://... atau /artikel" @endif
                                            value="{{ old($field, $item->{$field}) }}">
                                @endswitch
                            </div>
                        @endforeach

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('web.home-sections.edit', $section->id) }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if (isset($fields['icon']))
        <script>
            (function () {
                var input = document.getElementById('icon');
                var preview = document.getElementById('icon-preview');
                input.addEventListener('input', function () {
                    preview.className = 'bi bi-' + (/^[a-z0-9-]+$/.test(input.value) ? input.value : 'question-circle');
                });
            })();
        </script>
    @endif
@endsection
