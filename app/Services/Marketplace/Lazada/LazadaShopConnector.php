<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\BranchOffice;
use App\Models\MarketplaceShop;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menghubungkan toko Lazada pelanggan ke branch-nya (OAuth) dan menjaga
 * token-nya tetap hidup.
 *
 * Alur:
 *  1. authorizeUrl() -- pelanggan diarahkan ke halaman izin Lazada. `state`
 *     = token acak sekali pakai (berlaku 10 menit); company/branch/user-nya
 *     disimpan di cache dengan kunci token itu -- tidak bisa ditebak/dipakai
 *     ulang.
 *  2. Lazada kembali ke callback (marketplace.lazada.callback, URL
 *     /api/marketplace/lazada/callback) membawa `code` -> connect() memastikan
 *     user yang login = user yang memulai, menukar code jadi token, lalu
 *     menyimpan toko.
 *  3. freshAccessToken() dipakai sebelum memanggil API; token diperbarui
 *     otomatis saat hampir habis, dengan baris toko dikunci supaya 2 job
 *     tidak me-refresh bersamaan.
 */
class LazadaShopConnector
{
    private const STATE_TTL_MINUTES = 10;

    /** Perbarui token kalau sisa umurnya kurang dari ini. */
    private const REFRESH_BEFORE_HOURS = 24;

    public function __construct(private readonly LazadaClient $client)
    {
    }

    public function authorizeUrl(BranchOffice $branch, User $user): string
    {
        // State = token acak pendek; isinya (company/branch/user) disimpan di
        // cache, bukan ditaruh di URL -- URL tetap pendek & tanpa karakter
        // khusus (Lazada sempat menolak dengan "Missing parameter").
        $state = Str::random(40);

        Cache::put($this->stateKey($state), [
            'company_id' => $branch->company_id,
            'branch_office_id' => $branch->id,
            'user_id' => $user->id,
        ], now()->addMinutes(self::STATE_TTL_MINUTES));

        // Separator '&' ditulis eksplisit supaya tidak bergantung pada
        // setting arg_separator.output di php.ini server.
        return rtrim(config('services.lazada.auth_url'), '/').'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            // false: kalau seller sudah login Lazada di browser, langsung ke
            // halaman izin. Dengan 'true' Lazada memaksa login ulang, dan
            // setelah login itu Lazada sempat kehilangan parameter
            // ("Missing parameter" di api.lazada.co.id/oauth/authorize).
            'force_auth' => 'false',
            'redirect_uri' => config('services.lazada.redirect_uri') ?: route('marketplace.lazada.callback'),
            'client_id' => $this->client->appKey(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Tukar `code` dari callback jadi token & simpan tokonya.
     *
     * @throws LazadaConnectException pesan sudah ramah untuk ditampilkan
     */
    public function connect(string $code, string $state, User $user): MarketplaceShop
    {
        $context = $this->consumeState($state);

        // State hanya sah untuk user yang memulai proses ini.
        if ((string) $context['user_id'] !== (string) $user->id) {
            throw new LazadaConnectException('Proses ini dimulai oleh akun lain. Silakan ulangi dari menu Lazada.');
        }

        $branch = BranchOffice::where('id', $context['branch_office_id'])
            ->where('company_id', $context['company_id'])
            ->first();

        if (! $branch) {
            throw new LazadaConnectException('Branch tidak ditemukan. Silakan ulangi dari menu Lazada.');
        }

        try {
            $token = $this->client->auth('/auth/token/create', ['code' => $code]);
            $tokenAttributes = $this->tokenAttributes($token);
            $sellerId = $this->sellerIdFrom($token);
            $shopName = $this->shopName((string) $token['access_token'], $token);
        } catch (LazadaApiException $e) {
            throw new LazadaConnectException('Lazada menolak permintaan: '.$e->getMessage());
        }

        try {
            return $this->saveShop($branch, $context, $token, $tokenAttributes, $sellerId, $shopName);
        } catch (UniqueConstraintViolationException) {
            // Toko yang sama dihubungkan dari 2 tab bersamaan -- yang satu
            // sudah tersimpan duluan.
            throw new LazadaConnectException('Toko sedang dihubungkan dari tab lain. Muat ulang halaman untuk melihat hasilnya.');
        }
    }

    private function saveShop(BranchOffice $branch, array $context, array $token, array $tokenAttributes, string $sellerId, string $shopName): MarketplaceShop
    {
        return DB::transaction(function () use ($branch, $context, $token, $tokenAttributes, $sellerId, $shopName) {
            $shop = MarketplaceShop::where('provider', MarketplaceShop::PROVIDER_LAZADA)
                ->where('external_shop_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if ($shop && $shop->branch_office_id !== $branch->id) {
                throw new LazadaConnectException($shop->company_id === $branch->company_id
                    ? 'Toko Lazada ini sudah terhubung ke branch lain di company Anda. Putuskan dulu dari branch tersebut.'
                    : 'Toko Lazada ini sudah terhubung ke akun lain. Hubungi kami kalau ini toko Anda.');
            }

            $shop ??= new MarketplaceShop([
                'company_id' => $branch->company_id,
                'branch_office_id' => $branch->id,
                'provider' => MarketplaceShop::PROVIDER_LAZADA,
                'external_shop_id' => $sellerId,
            ]);

            $shop->fill($tokenAttributes + [
                'name' => $shopName,
                'country' => $token['country'] ?? null,
                'connected_by_user_id' => $context['user_id'],
                'status' => MarketplaceShop::STATUS_ACTIVE,
                'last_sync_error' => null,
            ])->save();

            return $shop;
        });
    }

    /**
     * Access token yang masih berlaku -- diperbarui dulu kalau hampir habis.
     *
     * @throws LazadaApiException token tidak bisa diperbarui (toko ditandai expired)
     */
    public function freshAccessToken(MarketplaceShop $shop): string
    {
        if (! $this->needsRefresh($shop)) {
            return (string) $shop->access_token;
        }

        $failure = null;

        $locked = DB::transaction(function () use ($shop, &$failure) {
            $locked = MarketplaceShop::whereKey($shop->id)->lockForUpdate()->firstOrFail();

            // Bisa jadi sudah diperbarui proses lain selagi menunggu kunci.
            if ($this->needsRefresh($locked)) {
                try {
                    $token = $this->client->auth('/auth/token/refresh', ['refresh_token' => $locked->refresh_token]);
                    $locked->fill($this->tokenAttributes($token))->save();
                } catch (LazadaApiException $e) {
                    $failure = $e;
                }
            }

            return $locked;
        });

        if ($failure) {
            // Ditandai di luar transaksi supaya tidak ikut ter-rollback.
            if ($failure->isTokenError() || $locked->refresh_token_expires_at?->isPast()) {
                $locked->forceFill([
                    'status' => MarketplaceShop::STATUS_EXPIRED,
                    'last_sync_error' => 'Izin toko sudah habis. Hubungkan ulang toko ini.',
                ])->save();
            }

            throw $failure;
        }

        $shop->setRawAttributes($locked->getAttributes(), true);

        return (string) $locked->access_token;
    }

    public function disconnect(MarketplaceShop $shop): void
    {
        $shop->forceFill([
            'status' => MarketplaceShop::STATUS_DISCONNECTED,
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
            'refresh_token_expires_at' => null,
        ])->save();
    }

    private function needsRefresh(MarketplaceShop $shop): bool
    {
        return ! $shop->access_token_expires_at
            || $shop->access_token_expires_at->lt(now()->addHours(self::REFRESH_BEFORE_HOURS));
    }

    private function tokenAttributes(array $token): array
    {
        if (empty($token['access_token']) || empty($token['refresh_token'])) {
            throw new LazadaApiException('Lazada tidak mengirim token.', 'InvalidResponse');
        }

        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'access_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0)),
            'refresh_token_expires_at' => now()->addSeconds((int) ($token['refresh_expires_in'] ?? 0)),
        ];
    }

    private function sellerIdFrom(array $token): string
    {
        $info = collect($token['country_user_info'] ?? []);
        // "country" di root huruf kecil ("id"), di country_user_info huruf
        // besar ("ID") -- dibandingkan tanpa peduli besar-kecil huruf.
        $country = strtolower((string) ($token['country'] ?? ''));
        $entry = $info->first(fn ($row) => strtolower((string) ($row['country'] ?? '')) === $country) ?? $info->first();
        $sellerId = (string) ($entry['seller_id'] ?? '');

        if ($sellerId === '') {
            throw new LazadaApiException('Akun ini tidak memiliki toko (seller) Lazada.', 'NoSeller');
        }

        return $sellerId;
    }

    /** Nama toko dari /seller/get; kalau gagal pakai nama akun. */
    private function shopName(string $accessToken, array $token): string
    {
        try {
            $seller = $this->client->get('/seller/get', [], $accessToken);
            $name = $seller['data']['name'] ?? null;
        } catch (LazadaApiException) {
            $name = null;
        }

        return Str::limit((string) ($name ?: ($token['account'] ?? 'Toko Lazada')), 250, '');
    }

    /**
     * @return array{company_id: string, branch_office_id: string, user_id: string}
     */
    private function consumeState(string $state): array
    {
        // Cache::pull = ambil sekaligus hapus: state yang sama tidak bisa
        // dipakai 2x, dan otomatis hangus setelah STATE_TTL_MINUTES.
        $data = preg_match('/^[A-Za-z0-9]{40}$/', $state) ? Cache::pull($this->stateKey($state)) : null;

        if (! is_array($data)) {
            throw new LazadaConnectException('Waktu menghubungkan sudah habis. Silakan klik "Hubungkan Toko" lagi.');
        }

        return $data;
    }

    private function stateKey(string $state): string
    {
        return 'lazada-oauth-state:'.$state;
    }
}
