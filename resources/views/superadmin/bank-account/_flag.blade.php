{{-- Peringatan rekening yang juga dipakai akun lain. Butuh $account dan $shared (BankAccountService::sharedCounts). --}}
@php($others = $shared[$account->account_number_hash] ?? 0)
@if ($others > 0)
    <span class="badge bg-danger-subtle text-danger">🔴 Rekening ini juga dipakai {{ $others }} akun lain</span>
@else
    <span class="text-muted small">-</span>
@endif
