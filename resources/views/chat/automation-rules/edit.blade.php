@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Aturan Automasi</h4>
            <p class="text-muted mb-0">"{{ $rule->name }}"</p>
        </div>
        <a href="{{ route('chat.automation-rules.index') }}" class="btn btn-light">
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
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @include('chat.automation-rules._form', [
                        'formAction' => route('chat.automation-rules.update', $rule->id),
                        'method' => 'PUT',
                        'rule' => $rule,
                        'tags' => $tags,
                        'dealStages' => $dealStages,
                        'teamMembers' => $teamMembers,
                        'formIdSuffix' => 'edit',
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
