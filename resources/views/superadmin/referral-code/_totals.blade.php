{{-- Ringkasan komisi: sudah cair vs masih tertahan. $totals dari ReferralCodeController::commissionTotals(). --}}
<div class="row g-2 mb-3">
    <div class="col-sm-6">
        <div class="alert alert-success d-flex align-items-center justify-content-between mb-0">
            <span><i class="ri-hand-coin-line me-1"></i> Komisi Sudah Cair</span>
            <strong>Rp {{ number_format($totals['available'], 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-0">
            <span><i class="ri-time-line me-1"></i> Komisi Tertahan</span>
            <strong>Rp {{ number_format($totals['pending'], 0, ',', '.') }}</strong>
        </div>
    </div>
</div>
