<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceShop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sinkron pesanan satu toko Lazada (GET /orders/get).
 *
 * - Hanya mengambil pesanan yang BERUBAH sejak kursor terakhir
 *   (update_after = orders_synced_until), urut updated_at naik, 100 per
 *   halaman, maksimal MAX_PAGES halaman per jalan -- sisanya diambil di jalan
 *   berikutnya karena kursor maju ke updated_at terbaru yang tersimpan.
 * - Sinkron pertama mengambil INITIAL_DAYS hari ke belakang.
 * - Upsert pada unique(marketplace_shop_id, external_order_id): aman dipanggil
 *   berulang/bersamaan, pesanan tidak pernah dobel.
 * - Dipanggil dari App\Jobs\SyncMarketplaceShopOrders (WithoutOverlapping per
 *   toko), bukan dari request web.
 */
class LazadaOrderSync
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 10;

    private const INITIAL_DAYS = 30;

    public function __construct(
        private readonly LazadaClient $client,
        private readonly LazadaShopConnector $connector,
    ) {
    }

    /**
     * @return int jumlah pesanan yang disimpan/diperbarui
     */
    public function sync(MarketplaceShop $shop): int
    {
        if (! $shop->isActive()) {
            return 0;
        }

        $cursor = CarbonImmutable::instance($shop->orders_synced_until ?? now()->subDays(self::INITIAL_DAYS));
        $saved = 0;

        try {
            $accessToken = $this->connector->freshAccessToken($shop);

            $newest = null;

            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $response = $this->client->get('/orders/get', [
                    'update_after' => $cursor->toIso8601String(),
                    'sort_by' => 'updated_at',
                    'sort_direction' => 'ASC',
                    'offset' => $page * self::PAGE_SIZE,
                    'limit' => self::PAGE_SIZE,
                ], $accessToken);

                $orders = $response['data']['orders'] ?? [];

                if ($orders === []) {
                    break;
                }

                $rows = array_map(fn (array $order) => $this->toRow($shop, $order), $orders);
                $saved += $this->upsert($rows);
                $newest = max($newest, collect($rows)->max('external_updated_at'));

                if (count($orders) < self::PAGE_SIZE) {
                    break;
                }
            }

            // Kursor maju ke updated_at terbaru yang sudah tersimpan. Pesanan
            // terakhir itu ikut terambil lagi di jalan berikutnya -- tidak
            // masalah karena upsert. Kalau halaman habis (MAX_PAGES), sisanya
            // (updated_at >= kursor) terambil di jalan berikutnya.
            if ($newest) {
                $cursor = CarbonImmutable::parse($newest);
            }

            $shop->forceFill([
                'orders_synced_until' => $cursor,
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ])->save();
        } catch (LazadaApiException $e) {
            $shop->forceFill([
                'orders_synced_until' => $cursor,
                'last_synced_at' => now(),
                'last_sync_error' => Str::limit($e->getMessage(), 480),
            ])->save();

            throw $e;
        }

        return $saved;
    }

    private function toRow(MarketplaceShop $shop, array $order): array
    {
        $now = now();
        $buyer = trim(($order['customer_first_name'] ?? '').' '.($order['customer_last_name'] ?? ''));

        return [
            'id' => (string) Str::uuid(),
            'marketplace_shop_id' => $shop->id,
            'company_id' => $shop->company_id,
            'branch_office_id' => $shop->branch_office_id,
            'external_order_id' => (string) $order['order_id'],
            'order_number' => isset($order['order_number']) ? (string) $order['order_number'] : null,
            'status' => isset($order['statuses'][0]) ? Str::limit((string) $order['statuses'][0], 40, '') : null,
            'buyer_name' => $buyer !== '' ? Str::limit($buyer, 250, '') : null,
            'total_amount' => (float) str_replace(',', '', (string) ($order['price'] ?? 0)),
            'item_count' => (int) ($order['items_count'] ?? 0),
            'payment_method' => isset($order['payment_method']) ? Str::limit((string) $order['payment_method'], 100, '') : null,
            'ordered_at' => $this->toDateTime($order['created_at'] ?? null),
            'external_updated_at' => $this->toDateTime($order['updated_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function upsert(array $rows): int
    {
        MarketplaceOrder::upsert(
            $rows,
            ['marketplace_shop_id', 'external_order_id'],
            ['order_number', 'status', 'buyer_name', 'total_amount', 'item_count', 'payment_method', 'ordered_at', 'external_updated_at', 'updated_at'],
        );

        return count($rows);
    }

    /** Lazada mengirim "2026-09-30 10:15:00 +0700"; disimpan dalam timezone aplikasi. */
    private function toDateTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'))->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
