@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Segmen</h4>
            <p class="text-muted mb-0">"{{ $segment->name }}"</p>
        </div>
        <a href="{{ route('chat.segments.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $filters = $segment->filters ?? []; @endphp

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.segments.update', $segment->id) }}" method="POST" class="row g-3">
                        @csrf
                        @method('PUT')

                        <div class="col-md-6">
                            <label class="form-label">Nama Segmen</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $segment->name) }}" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <input type="text" name="description" class="form-control" value="{{ old('description', $segment->description) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Punya Tag</label>
                            <select name="tag_id" class="form-select">
                                <option value="">- Tidak difilter -</option>
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}" @selected(old('tag_id', $filters['tag_id'] ?? null) === $tag->id)>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahap Deal</label>
                            <select name="deal_stage" class="form-select">
                                <option value="">- Tidak difilter -</option>
                                @foreach ($dealStages as $stageValue => $stageLabel)
                                    <option value="{{ $stageValue }}" @selected(old('deal_stage', $filters['deal_stage'] ?? null) === $stageValue)>{{ $stageLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cabang</label>
                            <select name="branch_office_id" class="form-select">
                                <option value="">- Tidak difilter -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_office_id', $filters['branch_office_id'] ?? null) === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tidak Dihubungi (hari)</label>
                            <input type="number" name="no_contact_days" class="form-control" min="1" value="{{ old('no_contact_days', $filters['no_contact_days'] ?? '') }}">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="has_overdue_task" value="1" id="wa-segment-overdue-edit" class="form-check-input" @checked(old('has_overdue_task', $filters['has_overdue_task'] ?? false))>
                                <label for="wa-segment-overdue-edit" class="form-check-label">Hanya yang punya tugas follow-up terlambat</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('chat.segments.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
