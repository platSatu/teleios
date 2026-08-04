@extends('layouts.dashboard')

@section('content')
    <style>
        .transfer-wizard {
            max-width: 640px;
            margin: 0 auto;
        }

        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .wizard-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 16px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: rgba(0, 0, 0, .1);
            z-index: 0;
        }

        .wizard-step.done:not(:last-child)::after {
            background: var(--bs-primary);
        }

        .wizard-step .circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto .5rem;
            position: relative;
            z-index: 1;
            font-weight: 600;
            font-size: .8125rem;
        }

        .wizard-step.active .circle,
        .wizard-step.done .circle {
            background: var(--bs-primary);
            color: #fff;
        }

        .wizard-step .label {
            font-size: .75rem;
            color: #888;
        }

        .wizard-panel {
            display: none;
        }

        .wizard-panel.active {
            display: block;
        }

        .recipient-found-card {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: .5rem;
            padding: 1rem;
            display: none;
            align-items: center;
            gap: .75rem;
        }

        .recipient-found-card.show {
            display: flex;
        }

        .recipient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bs-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
    </style>

    <div class="transfer-wizard">
        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="{{ url()->previous() }}" class="btn btn-icon btn-outline-secondary btn-sm rounded-circle">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h4 class="mb-1">Transfer Saldo</h4>
                <p class="text-muted mb-0">Saldo Anda: Rp {{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</p>
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

        @if (! $hasPin)
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <i class="ri-lock-2-line fs-1 text-muted d-block mb-3"></i>
                    <h5 class="mb-2">Buat PIN Transaksi Dulu</h5>
                    <p class="text-muted mb-3">Untuk keamanan, transfer saldo memerlukan PIN 6 digit. Buat PIN Anda terlebih dahulu.</p>
                    <a href="{{ route('user-settings.pin.edit') }}" class="btn btn-primary">
                        <i class="ri-key-2-line"></i> Buat PIN Sekarang
                    </a>
                </div>
            </div>
        @else
            <div class="wizard-steps">
                <div class="wizard-step active" data-step="1">
                    <div class="circle">1</div>
                    <div class="label">Penerima</div>
                </div>
                <div class="wizard-step" data-step="2">
                    <div class="circle">2</div>
                    <div class="label">Jumlah</div>
                </div>
                <div class="wizard-step" data-step="3">
                    <div class="circle">3</div>
                    <div class="label">Konfirmasi</div>
                </div>
                <div class="wizard-step" data-step="4">
                    <div class="circle">4</div>
                    <div class="label">PIN</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="{{ route('dashboard.wallet-transfer.store') }}" method="POST" id="transfer-form">
                        @csrf
                        <input type="hidden" name="receiver_id" id="field_receiver_id">
                        <input type="hidden" name="amount" id="field_amount">
                        <input type="hidden" name="note" id="field_note">

                        {{-- Step 1: Recipient --}}
                        <div class="wizard-panel active" data-panel="1">
                            <label class="form-label">No HP atau Email Penerima</label>
                            <div class="input-group mb-2">
                                <input type="text" id="identifier" class="form-control" placeholder="08xxxxxxxxxx atau email" autocomplete="off">
                                <button type="button" class="btn btn-outline-primary" id="btn-lookup">Cari</button>
                            </div>
                            <div id="lookup-feedback" class="fs-13 mb-3"></div>

                            <div class="recipient-found-card mb-3" id="recipient-card">
                                <div class="recipient-avatar" id="recipient-initial"></div>
                                <div>
                                    <p class="mb-0 fw-semibold" id="recipient-name"></p>
                                    <p class="mb-0 text-muted fs-13" id="recipient-contact"></p>
                                </div>
                            </div>

                            <button type="button" class="btn btn-primary w-100" id="btn-step1-next" disabled>
                                Lanjut <i class="ri-arrow-right-line"></i>
                            </button>
                        </div>

                        {{-- Step 2: Amount --}}
                        <div class="wizard-panel" data-panel="2">
                            <p class="text-muted fs-13 mb-2">Mengirim ke <strong id="summary-name-2"></strong></p>
                            <label class="form-label">Jumlah Transfer</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" id="amount_display" class="form-control" placeholder="Contoh: 50.000" autocomplete="off">
                            </div>
                            <p class="text-muted fs-12 mb-4">Minimal transfer Rp {{ number_format(1000, 0, ',', '.') }}. Saldo Anda: Rp {{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</p>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-fill" data-back="1">
                                    <i class="ri-arrow-left-line"></i> Kembali
                                </button>
                                <button type="button" class="btn btn-primary flex-fill" id="btn-step2-next">
                                    Lanjut <i class="ri-arrow-right-line"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Step 3: Confirmation --}}
                        <div class="wizard-panel" data-panel="3">
                            <h6 class="mb-3">Periksa Kembali</h6>
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Penerima</span>
                                    <span class="fw-semibold" id="summary-name-3"></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Jumlah</span>
                                    <span class="fw-semibold text-primary" id="summary-amount-3"></span>
                                </div>
                            </div>

                            <label class="form-label">Catatan (opsional)</label>
                            <textarea class="form-control mb-4" id="note_input" rows="2" placeholder="Contoh: Bayar patungan"></textarea>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-fill" data-back="2">
                                    <i class="ri-arrow-left-line"></i> Kembali
                                </button>
                                <button type="button" class="btn btn-primary flex-fill" id="btn-step3-next">
                                    Kirim Sekarang <i class="ri-arrow-right-line"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Step 4: PIN --}}
                        <div class="wizard-panel" data-panel="4">
                            <div class="text-center mb-4">
                                <i class="ri-shield-keyhole-line fs-1 text-primary d-block mb-2"></i>
                                <h6 class="mb-1">Masukkan PIN Transaksi</h6>
                                <p class="text-muted fs-13">Konfirmasi transfer <span id="summary-amount-4"></span> ke <span id="summary-name-4"></span></p>
                            </div>

                            <input type="password" name="pin" inputmode="numeric" maxlength="6" class="form-control form-control-lg text-center mb-4" style="letter-spacing: .5em; max-width: 220px; margin-left: auto; margin-right: auto;" placeholder="••••••" required>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-fill" data-back="3">
                                    <i class="ri-arrow-left-line"></i> Kembali
                                </button>
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="ri-check-line"></i> Konfirmasi Transfer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var currentStep = 1;
        var recipient = null;
        var amount = 0;

        function goToStep(step) {
            document.querySelectorAll('.wizard-panel').forEach(function (el) {
                el.classList.toggle('active', el.dataset.panel === String(step));
            });
            document.querySelectorAll('.wizard-step').forEach(function (el) {
                var s = parseInt(el.dataset.step, 10);
                el.classList.toggle('active', s === step);
                el.classList.toggle('done', s < step);
            });
            currentStep = step;
        }

        document.querySelectorAll('[data-back]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                goToStep(parseInt(btn.dataset.back, 10));
            });
        });

        // Step 1: lookup recipient
        document.getElementById('btn-lookup').addEventListener('click', function () {
            var identifier = document.getElementById('identifier').value.trim();
            var feedback = document.getElementById('lookup-feedback');
            var card = document.getElementById('recipient-card');
            var nextBtn = document.getElementById('btn-step1-next');

            if (!identifier) {
                feedback.textContent = 'Masukkan no HP atau email terlebih dahulu.';
                feedback.className = 'fs-13 mb-3 text-danger';
                return;
            }

            fetch('{{ route('dashboard.wallet-transfer.lookup') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ identifier: identifier }),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.found) {
                        recipient = data.user;
                        feedback.textContent = '';
                        card.classList.add('show');
                        document.getElementById('recipient-initial').textContent = data.user.name.charAt(0).toUpperCase();
                        document.getElementById('recipient-name').textContent = data.user.name;
                        document.getElementById('recipient-contact').textContent = data.user.contact;
                        document.getElementById('field_receiver_id').value = data.user.id;
                        document.getElementById('summary-name-2').textContent = data.user.name;
                        document.getElementById('summary-name-3').textContent = data.user.name;
                        document.getElementById('summary-name-4').textContent = data.user.name;
                        nextBtn.disabled = false;
                    } else {
                        recipient = null;
                        card.classList.remove('show');
                        nextBtn.disabled = true;
                        feedback.textContent = data.message;
                        feedback.className = 'fs-13 mb-3 text-danger';
                    }
                });
        });

        document.getElementById('btn-step1-next').addEventListener('click', function () {
            if (recipient) {
                goToStep(2);
            }
        });

        // Step 2: amount, formatted with thousand separators
        var amountDisplay = document.getElementById('amount_display');
        amountDisplay.addEventListener('input', function () {
            var digitsOnly = amountDisplay.value.replace(/\D/g, '');
            amountDisplay.value = digitsOnly.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        });

        document.getElementById('btn-step2-next').addEventListener('click', function () {
            var digitsOnly = amountDisplay.value.replace(/\D/g, '');
            amount = parseInt(digitsOnly || '0', 10);

            if (!amount || amount < 1000) {
                alert('Masukkan jumlah transfer minimal Rp 1.000.');
                return;
            }

            document.getElementById('field_amount').value = amount;
            var formatted = 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            document.getElementById('summary-amount-3').textContent = formatted;
            document.getElementById('summary-amount-4').textContent = formatted;
            goToStep(3);
        });

        // Step 3: confirmation -> PIN step
        document.getElementById('btn-step3-next').addEventListener('click', function () {
            document.getElementById('field_note').value = document.getElementById('note_input').value;
            goToStep(4);
        });
    });
    </script>
@endsection
