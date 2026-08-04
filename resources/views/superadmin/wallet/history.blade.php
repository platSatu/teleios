@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Wallet — {{ $wallet->user->name ?? '-' }}</h4>
        <a href="{{ route('wallet.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
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

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-1">Saldo Saat Ini</p>
                    <h3 class="mb-3">Rp {{ number_format($wallet->balance, 0, ',', '.') }}</h3>
                    <p class="text-muted mb-3">{{ $wallet->user->email ?? '-' }} &middot; {{ $wallet->currency }}</p>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#creditModal">
                            <i class="ri-add-line"></i> Tambah Saldo
                        </button>
                        <button type="button" class="btn btn-danger flex-fill" data-bs-toggle="modal" data-bs-target="#debitModal">
                            <i class="ri-subtract-line"></i> Kurangi Saldo
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Aksi Admin</h5>
                    @forelse ($adminActions as $action)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between">
                                <span class="badge {{ $action->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ $action->direction }} Rp {{ number_format($action->amount, 0, ',', '.') }}
                                </span>
                                <span class="text-muted small">{{ $action->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <div class="small text-muted mt-1">{{ $action->reason }}</div>
                            <div class="small text-muted">oleh {{ $action->admin->name ?? '-' }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Belum ada aksi admin.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">History Saldo Sebelum &amp; Sesudah (Ledger)</h5>

                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Arah</th>
                                    <th>Nominal</th>
                                    <th>Saldo Sebelum</th>
                                    <th>Saldo Sesudah</th>
                                    <th>Sumber</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($entries as $entry)
                                    <tr>
                                        <td>
                                            <span class="badge {{ $entry->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ $entry->direction }}
                                            </span>
                                        </td>
                                        <td>Rp {{ number_format($entry->amount, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($entry->balance_before, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($entry->balance_after, 0, ',', '.') }}</td>
                                        <td class="small text-muted">{{ $entry->transaction->transaction_type ?? '-' }}</td>
                                        <td>{{ $entry->created_at->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Belum ada entri ledger.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $entries->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tambah Saldo --}}
    <div class="modal fade" id="creditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('wallet.credit', $wallet->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Saldo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nominal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" autocomplete="off" class="form-control" id="credit_amount_display" placeholder="Contoh : 100.000" required>
                            </div>
                            <input type="hidden" name="amount" id="credit_amount">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alasan</label>
                            <textarea name="reason" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Kurangi Saldo --}}
    <div class="modal fade" id="debitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('wallet.debit', $wallet->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Kurangi Saldo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nominal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" autocomplete="off" class="form-control" id="debit_amount_display" placeholder="Contoh : 100.000" required>
                            </div>
                            <input type="hidden" name="amount" id="debit_amount">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alasan</label>
                            <textarea name="reason" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Same script tag ordering caveat as elsewhere in this app: Bootstrap's
         JS lives in a <script> tag at the very bottom of layouts.dashboard,
         after this content — wrapping in DOMContentLoaded avoids relying on
         load order for the modal triggers / formatter both. --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function attachIdrFormatter(displayId, hiddenId) {
            var display = document.getElementById(displayId);
            var hidden = document.getElementById(hiddenId);
            if (!display || !hidden) {
                return;
            }

            function formatWithDots(digits) {
                return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function countDigitsBeforeCursor(value, cursorPos) {
                var count = 0;
                for (var i = 0; i < cursorPos && i < value.length; i++) {
                    if (value[i] >= '0' && value[i] <= '9') {
                        count++;
                    }
                }
                return count;
            }

            function cursorPosForDigitCount(value, digitCount) {
                if (digitCount <= 0) {
                    return 0;
                }
                var count = 0;
                for (var i = 0; i < value.length; i++) {
                    if (value[i] >= '0' && value[i] <= '9') {
                        count++;
                        if (count === digitCount) {
                            return i + 1;
                        }
                    }
                }
                return value.length;
            }

            display.addEventListener('input', function () {
                var cursorPos = display.selectionStart;
                var digitsBeforeCursor = countDigitsBeforeCursor(display.value, cursorPos);

                var digitsOnly = display.value.replace(/\D/g, '');
                var formatted = formatWithDots(digitsOnly);

                display.value = formatted;
                hidden.value = digitsOnly;

                var newCursorPos = cursorPosForDigitCount(formatted, digitsBeforeCursor);
                display.setSelectionRange(newCursorPos, newCursorPos);
            });
        }

        attachIdrFormatter('credit_amount_display', 'credit_amount');
        attachIdrFormatter('debit_amount_display', 'debit_amount');
    });
    </script>
@endsection
