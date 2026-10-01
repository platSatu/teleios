<?php

namespace App\Jobs;

use App\Models\MarketplaceShop;
use App\Services\Marketplace\Lazada\LazadaApiException;
use App\Services\Marketplace\Lazada\LazadaOrderSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Sinkron pesanan satu toko marketplace. Dikirim oleh command
 * marketplace:sync-orders (terjadwal) dan tombol "Sinkron Sekarang".
 *
 * WithoutOverlapping per toko: tombol yang diklik berkali-kali / jadwal yang
 * bertabrakan tidak membuat 2 sinkron toko yang sama berjalan bersamaan;
 * yang datang belakangan dibuang (dontRelease), jalan berikutnya akan
 * menyusul karena kursornya tersimpan.
 */
class SyncMarketplaceShopOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(public string $shopId)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('marketplace-shop-sync:'.$this->shopId))->dontRelease()->expireAfter(600)];
    }

    public function handle(LazadaOrderSync $lazada): void
    {
        $shop = MarketplaceShop::find($this->shopId);

        if (! $shop || ! $shop->isActive()) {
            return;
        }

        try {
            match ($shop->provider) {
                MarketplaceShop::PROVIDER_LAZADA => $lazada->sync($shop),
                default => null,
            };
        } catch (LazadaApiException $e) {
            // Token mati tidak akan sembuh dengan diulang -- tokonya sudah
            // ditandai "perlu dihubungkan ulang". Error lain boleh diulang.
            if ($e->isTokenError()) {
                return;
            }

            throw $e;
        }
    }
}
