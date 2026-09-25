<?php

use App\Models\CompanyLimitUsage;
use App\Models\Voucher;
use App\Services\PackageLimitService;
use Illuminate\Database\Migrations\Migration;

/**
 * Kuota kiriman sekarang per bulan (PackageLimitService::currentPeriod()).
 * Counter lama tercatat dengan periode = masa subscription; tanpa migrasi
 * ini counter itu tidak terbaca lagi dan pelanggan aktif dapat kuota
 * penuh gratis saat deploy. Di sini pemakaian yang sudah ada dipindah ke
 * periode bulan berjalan dari voucher aktifnya (angka terpakai tetap).
 * Counter milik voucher yang sudah tidak aktif dibiarkan (histori).
 */
return new class extends Migration
{
    public function up(): void
    {
        $limits = app(PackageLimitService::class);

        CompanyLimitUsage::query()
            ->whereNotNull('subscription_id')
            ->chunkById(200, function ($usages) use ($limits) {
                $vouchers = Voucher::query()
                    ->currentlyActive()
                    ->whereIn('subscription_id', $usages->pluck('subscription_id'))
                    ->get()
                    ->keyBy('subscription_id');

                foreach ($usages as $usage) {
                    $voucher = $vouchers->get($usage->subscription_id);

                    if (! $voucher) {
                        continue;
                    }

                    [$start, $end] = $limits->currentPeriod($voucher);

                    $usage->forceFill(['period_start' => $start, 'period_end' => $end])->save();
                }
            });
    }

    public function down(): void
    {
        // Tidak dikembalikan: periode lama tidak dipakai lagi.
    }
};
