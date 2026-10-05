@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    @if ($copyFrom)
                        <h4 class="mb-1">Copy Package Limit</h4>
                        <p class="text-muted mb-4">Disalin dari {{ $copyFrom->package->name ?? '-' }} · {{ $copyFrom->limitMetric->name ?? '-' }}. Ganti package atau metric-nya, lalu simpan.</p>
                    @else
                        <h4 class="mb-4">Tambah Package Limit</h4>
                    @endif

                    <form action="{{ route('package-limit.store') }}" method="POST">
                        @include('superadmin.package-limit._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
