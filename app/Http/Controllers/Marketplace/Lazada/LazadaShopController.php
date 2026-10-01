<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Lazada\Concerns\ScopesToActiveBranch;
use App\Jobs\SyncMarketplaceShopOrders;
use App\Services\Marketplace\Lazada\LazadaClient;
use App\Services\Marketplace\Lazada\LazadaConnectException;
use App\Services\Marketplace\Lazada\LazadaShopConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Marketplace > Lazada > Toko: daftar toko Lazada di branch yang sedang
 * dibuka, hubungkan (OAuth), sinkron manual, dan putuskan. Logika ada di
 * App\Services\Marketplace\Lazada\* -- controller ini hanya meneruskan.
 */
class LazadaShopController extends Controller
{
    use ScopesToActiveBranch;

    /** Jeda minimum tombol "Sinkron Sekarang" per toko (detik). */
    private const MANUAL_SYNC_COOLDOWN = 60;

    public function index(Request $request, LazadaClient $client): View
    {
        $branch = $this->activeBranchOrFail($request);

        $shops = $this->lazadaShopsQuery($branch)
            ->withCount('orders')
            ->latest()
            ->get();

        return view('marketplace.lazada.shops.index', [
            'branch' => $branch,
            'shops' => $shops,
            'isConfigured' => $client->isConfigured(),
        ]);
    }

    public function connect(Request $request, LazadaClient $client, LazadaShopConnector $connector): RedirectResponse
    {
        if (! $client->isConfigured()) {
            return back()->with('error', 'Integrasi Lazada belum diaktifkan oleh admin Bizbos.');
        }

        $branch = $this->activeBranchOrFail($request);

        return redirect()->away($connector->authorizeUrl($branch, $request->user()));
    }

    /**
     * Tujuan kembali dari halaman izin Lazada (URL terdaftar di Lazada:
     * /api/marketplace/lazada/callback).
     */
    public function callback(Request $request, LazadaShopConnector $connector): RedirectResponse
    {
        $index = redirect()->route('marketplace.lazada.shops.index');

        if (! $request->filled('code') || ! $request->filled('state')) {
            return $index->with('error', 'Toko belum terhubung karena izin di Lazada dibatalkan atau tidak lengkap.');
        }

        try {
            $shop = $connector->connect((string) $request->query('code'), (string) $request->query('state'), $request->user());
        } catch (LazadaConnectException $e) {
            return $index->with('error', $e->getMessage());
        }

        SyncMarketplaceShopOrders::dispatch($shop->id);

        return $index->with('success', "Toko {$shop->name} berhasil terhubung. Pesanan sedang disinkronkan, cek beberapa saat lagi ya.");
    }

    public function sync(Request $request, string $id): RedirectResponse
    {
        $shop = $this->findLazadaShopOrFail($request, $id);

        if (! $shop->isActive()) {
            return back()->with('error', 'Hubungkan ulang toko ini dulu sebelum sinkron.');
        }

        $key = 'lazada-manual-sync:'.$shop->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('error', 'Sinkron baru saja dijalankan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');
        }

        RateLimiter::hit($key, self::MANUAL_SYNC_COOLDOWN);
        SyncMarketplaceShopOrders::dispatch($shop->id);

        return back()->with('success', 'Sinkron pesanan sedang berjalan. Data akan muncul dalam beberapa saat.');
    }

    public function disconnect(Request $request, string $id, LazadaShopConnector $connector): RedirectResponse
    {
        $shop = $this->findLazadaShopOrFail($request, $id);
        $connector->disconnect($shop);

        return back()->with('success', "Toko {$shop->name} sudah diputus. Riwayat pesanannya tetap tersimpan.");
    }
}
