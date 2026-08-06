@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="{{ route('chat.third-party.google-form.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali">
                <i class="ri-arrow-left-line"></i>
            </a>
            <h4 class="mb-0">Tambah Integrasi Google Form</h4>
        </div>

        <form action="{{ route('chat.third-party.google-form.store') }}" method="POST">
            @include('chat.third-party.google-form._form', ['integration' => null])
        </form>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
