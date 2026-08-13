@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Deal</h4>
            <p class="text-muted mb-0">
                Untuk "{{ $deal->customer->name ?? ('+'.($deal->customer->phone ?? '-')) }}".
            </p>
        </div>
        <a href="{{ route('chat.deals.index') }}" class="btn btn-light">
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

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.deals.update', $deal->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Judul Deal</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $deal->title) }}" required maxlength="200">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nilai (Rp)</label>
                            <input type="number" name="value" class="form-control" value="{{ old('value', $deal->value) }}" min="0" step="0.01">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Target Closing</label>
                            <input type="date" name="expected_close_at" class="form-control" value="{{ old('expected_close_at', $deal->expected_close_at?->format('Y-m-d')) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ditugaskan ke</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Belum ditugaskan</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected(old('assigned_to', $deal->assigned_to) === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('chat.deals.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
