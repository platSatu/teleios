<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceShopOrders;
use App\Models\MarketplaceShop;
use Illuminate\Console\Command;

/**
 * Antrekan sinkron pesanan untuk setiap toko marketplace yang aktif.
 * Dijadwalkan tiap 15 menit (bootstrap/app.php). Command ini hanya
 * mengantrekan -- pekerjaan berat (HTTP ke Lazada) jalan di queue worker,
 * satu job per toko, supaya toko yang lambat tidak menahan toko lain.
 */
class SyncMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:sync-orders';

    protected $description = 'Antrekan sinkron pesanan untuk semua toko marketplace yang aktif';

    public function handle(): int
    {
        $count = 0;

        MarketplaceShop::where('status', MarketplaceShop::STATUS_ACTIVE)
            ->select('id')
            ->chunkById(200, function ($shops) use (&$count) {
                foreach ($shops as $shop) {
                    SyncMarketplaceShopOrders::dispatch($shop->id);
                    $count++;
                }
            });

        $this->info("Sinkron diantrekan: {$count} toko");

        return self::SUCCESS;
    }
}
