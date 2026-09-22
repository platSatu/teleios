<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris per eksekusi "Transfer Fee" bulanan (satu BranchOffice +
 * satu periode). Lihat migration create_pengajar_fee_transfers_table.php
 * untuk alasan tidak ada tabel item terpisah -- breakdown per pengajar
 * di-derive lewat sesiJadwalKelas() (GROUP BY pengajar_id) alih-alih
 * disimpan ulang di sini.
 */
class PengajarFeeTransfer extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'pengajar_fee_transfers';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'wallet_id',
        'periode_year',
        'periode_month',
        'total_debited',
        'pengajar_count',
        'sesi_count',
        'executed_by',
    ];

    protected $casts = [
        'periode_year' => 'integer',
        'periode_month' => 'integer',
        'total_debited' => 'decimal:2',
        'pengajar_count' => 'integer',
        'sesi_count' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /** Semua App\Models\JadwalKelas yang dibayarkan lewat transfer ini. */
    public function sesiJadwalKelas(): HasMany
    {
        return $this->hasMany(JadwalKelas::class, 'fee_transfer_id');
    }

    /** Label "September 2026" dari periode_year/periode_month, buat ditampilkan di riwayat. */
    public function periodeLabel(): string
    {
        return \Illuminate\Support\Carbon::createFromDate($this->periode_year, $this->periode_month, 1)
            ->translatedFormat('F Y');
    }
}
