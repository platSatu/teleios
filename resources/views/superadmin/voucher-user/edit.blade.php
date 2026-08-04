@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Voucher</h4>

                    <form action="{{ route('voucher-user.update', $voucherUser->id) }}" method="POST">
                        @include('superadmin.voucher-user._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
