@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Provider AI</h4>

                    <form action="{{ route('wa-ai-bot-provider.store') }}" method="POST">
                        @include('superadmin.wa-ai-bot-provider._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
