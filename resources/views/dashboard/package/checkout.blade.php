@extends('layouts.dashboard')

@section('content')
    <style>
        .checkout-summary-card {
            position: sticky;
            top: 1rem;
        }

        .code-input-group .btn {
            min-width: 96px;
        }

        .code-feedback {
            font-size: .8125rem;
            min-height: 1.25rem;
        }

        .code-feedback.text-success i,
        .code-feedback.text-danger i {
            vertical-align: -1px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .375rem 0;
        }

        .price-row.discount span:last-child {
            color: #17c666;
        }

        .price-total {
            border-top: 1px dashed rgba(0, 0, 0, .1);
            margin-top: .5rem;
            padding-top: .75rem;
        }
    </style>

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('dashboard.package.index') }}" class="btn btn-icon btn-outline-secondary btn-sm rounded-circle">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">Checkout</h4>
            <p class="text-muted mb-0">Selesaikan pembelian package Anda.</p>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- Package summary --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <span class="avatar-item avatar-lg rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center">
                            <i class="ri-box-3-line fs-3"></i>
                        </span>
                        @if ($package->categoryApplication)
                            <span class="badge bg-info-subtle text-info fw-medium">
                                {{ $package->categoryApplication->name }}
                            </span>
                        @endif
                    </div>

                    <h4 class="mb-2">{{ $package->name }}</h4>
                    <p class="text-muted mb-3">{{ $package->description ?: 'Tidak ada deskripsi.' }}</p>

                    <div class="d-flex align-items-center gap-2 text-muted fs-14">
                        <i class="ri-time-line"></i>
                        <span>Masa aktif {{ $package->duration }} hari setelah kode aktivasi di-redeem</span>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="mb-3">Punya Kode Promo atau Referral?</h6>

                    <div class="mb-3">
                        <label class="form-label fs-14">Kode Promo</label>
                        <div class="input-group code-input-group">
                            <input type="text" id="kode_voucher" name="kode_voucher" class="form-control" placeholder="Masukkan kode promo" autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="btn-apply-promo">
                                <span class="btn-label">Gunakan</span>
                            </button>
                        </div>
                        <div class="code-feedback mt-1" id="feedback-promo"></div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fs-14">Kode Referral</label>

                        @if ($linkedReferrer)
                            {{-- Already linked from a previous purchase — nothing left
                                 to type. That referrer keeps earning commission on this
                                 and every future purchase automatically. --}}
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-0 py-2 px-3">
                                <i class="ri-links-line fs-5"></i>
                                <div class="fs-13">
                                    Anda terhubung dengan kode referral <code>{{ $linkedReferrer->referralCode->code ?? '-' }}</code>
                                    milik {{ $linkedReferrer->name }}. Tidak perlu input lagi.
                                </div>
                            </div>
                        @else
                            <div class="input-group code-input-group">
                                <input type="text" id="kode_referral" name="kode_referral" class="form-control" placeholder="Masukkan kode referral" autocomplete="off">
                                <button type="button" class="btn btn-outline-primary" id="btn-apply-referral">
                                    <span class="btn-label">Gunakan</span>
                                </button>
                            </div>
                            <div class="code-feedback mt-1" id="feedback-referral"></div>
                            <div class="text-muted fs-12 mt-1">Kode referral hanya perlu dimasukkan sekali.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment summary --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm checkout-summary-card">
                <div class="card-body p-4">
                    <h6 class="mb-3">Ringkasan Pembayaran</h6>

                    <div class="price-row">
                        <span class="text-muted">Harga Package</span>
                        <span id="summary-subtotal">Rp {{ number_format($package->price, 0, ',', '.') }}</span>
                    </div>
                    <div class="price-row discount d-none" id="row-discount-promo">
                        <span class="text-muted">Diskon Promo (<span id="discount-promo-percent">0</span>%)</span>
                        <span id="discount-promo-amount">- Rp 0</span>
                    </div>
                    <div class="price-row discount d-none" id="row-discount-referral">
                        <span class="text-muted">Diskon Referral (<span id="discount-referral-percent">0</span>%)</span>
                        <span id="discount-referral-amount">- Rp 0</span>
                    </div>

                    <div class="price-row price-total">
                        <span class="fw-semibold">Total Bayar</span>
                        <h5 class="mb-0 text-primary" id="summary-total">Rp {{ number_format($package->price, 0, ',', '.') }}</h5>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between mb-3 fs-14">
                        <span class="text-muted"><i class="ri-wallet-3-line me-1"></i>Saldo Wallet</span>
                        <span class="fw-semibold">Rp {{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</span>
                    </div>

                    <form action="{{ route('dashboard.package.checkout.store', $package->id) }}" method="POST" id="checkout-form">
                        @csrf
                        <input type="hidden" name="kode_voucher" id="input_kode_voucher">
                        <input type="hidden" name="kode_referral" id="input_kode_referral">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-secure-payment-line"></i> Bayar Sekarang
                        </button>
                    </form>
                    <p class="text-muted fs-12 mt-2 mb-0 text-center">Pembayaran langsung dipotong dari saldo wallet Anda.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var PRICE = {{ (float) $package->price }};
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        var state = {
            promo: null,   // { percent }
            referral: null // { percent }
        };

        function formatRupiah(value) {
            return 'Rp ' + Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function fetchJson(url, code) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ code: code }),
            }).then(function (res) { return res.json(); });
        }

        function setFeedback(elId, valid, message) {
            var el = document.getElementById(elId);
            el.textContent = message;
            el.className = 'code-feedback mt-1 ' + (valid ? 'text-success' : 'text-danger');
        }

        function recalculate() {
            var total = PRICE;

            var promoRow = document.getElementById('row-discount-promo');
            var referralRow = document.getElementById('row-discount-referral');

            if (state.promo) {
                var promoAmount = PRICE * (state.promo.percent / 100);
                total -= promoAmount;
                document.getElementById('discount-promo-percent').textContent = state.promo.percent;
                document.getElementById('discount-promo-amount').textContent = '- ' + formatRupiah(promoAmount);
                promoRow.classList.remove('d-none');
            } else {
                promoRow.classList.add('d-none');
            }

            if (state.referral) {
                var referralAmount = PRICE * (state.referral.percent / 100);
                total -= referralAmount;
                document.getElementById('discount-referral-percent').textContent = state.referral.percent;
                document.getElementById('discount-referral-amount').textContent = '- ' + formatRupiah(referralAmount);
                referralRow.classList.remove('d-none');
            } else {
                referralRow.classList.add('d-none');
            }

            total = Math.max(0, total);
            document.getElementById('summary-total').textContent = formatRupiah(total);
        }

        document.getElementById('btn-apply-promo').addEventListener('click', function () {
            var code = document.getElementById('kode_voucher').value.trim();
            if (!code) {
                setFeedback('feedback-promo', false, 'Masukkan kode promo terlebih dahulu.');
                return;
            }

            fetchJson('{{ route('dashboard.package.checkout.apply-promo', $package->id) }}', code).then(function (data) {
                setFeedback('feedback-promo', data.valid, data.message);
                if (data.valid) {
                    state.promo = { percent: data.discount_percent };
                    document.getElementById('input_kode_voucher').value = code;
                } else {
                    state.promo = null;
                    document.getElementById('input_kode_voucher').value = '';
                }
                recalculate();
            });
        });

        // Referral input/button don't exist in the DOM at all once the
        // user is already linked to a referrer (see the linkedReferrer
        // check above) — nothing to wire up in that case.
        var btnApplyReferral = document.getElementById('btn-apply-referral');
        if (btnApplyReferral) {
            btnApplyReferral.addEventListener('click', function () {
                var code = document.getElementById('kode_referral').value.trim();
                if (!code) {
                    setFeedback('feedback-referral', false, 'Masukkan kode referral terlebih dahulu.');
                    return;
                }

                fetchJson('{{ route('dashboard.package.checkout.apply-referral', $package->id) }}', code).then(function (data) {
                    setFeedback('feedback-referral', data.valid, data.message);
                    if (data.valid) {
                        state.referral = { percent: data.discount_percent };
                        document.getElementById('input_kode_referral').value = code;
                    } else {
                        state.referral = null;
                        document.getElementById('input_kode_referral').value = '';
                    }
                    recalculate();
                });
            });
        }
    });
    </script>
@endsection
