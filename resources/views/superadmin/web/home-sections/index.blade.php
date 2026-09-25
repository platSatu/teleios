@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Susunan Beranda</h4>
                    <p class="text-muted mb-0">Urutan section di beranda website. Section bawaan mengambil data dari menunya masing-masing.</p>
                </div>
            </div>

            @include('components.notifikasi')

            @include('superadmin.web.home-sections._sections-table', ['sections' => $sections])
        </div>
    </div>
@endsection
