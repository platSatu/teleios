<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Models\WaApiKey;
use App\Models\WaApiRequestLog;
use App\Services\PackageLimitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pencatatan & ringkasan pemakaian WA API pihak ketiga.
 *
 * Kuotanya sendiri TIDAK dihitung di sini — pesan yang keluar lewat API
 * sudah memotong kuota paket (broadcast_send) yang sama dengan
 * broadcast/auto-reply lewat InboxService::guardPackageLimit(), jadi API
 * punya hak & batas yang persis sama dengan pemakaian dari dashboard.
 * Service ini cuma menambahkan JEJAK per request (App\Models\
 * WaApiRequestLog) supaya pemakaian lewat API bisa dilihat & dihitung
 * terpisah.
 */
class WaApiUsageService
{
    public function __construct(protected PackageLimitService $packageLimits)
    {
    }

    /**
     * Catat satu request. Sengaja TIDAK PERNAH melempar exception —
     * kalau pencatatan riwayat gagal (mis. DB sesaat bermasalah), respons
     * ke pihak ketiga (dan pesan yang mungkin SUDAH terkirim) tidak boleh
     * ikut gagal gara-gara ini. Kegagalannya dicatat ke log saja.
     */
    public function record(WaApiKey $apiKey, string $endpoint, string $status, int $httpStatus, array $attributes = []): void
    {
        try {
            WaApiRequestLog::create(array_merge([
                'company_id' => $apiKey->company_id,
                'wa_api_key_id' => $apiKey->id,
                'device_id' => $apiKey->device_id,
                'endpoint' => $endpoint,
                'status' => $status,
                'http_status' => $httpStatus,
            ], array_intersect_key($attributes, array_flip([
                'recipient',
                'message_length',
                'wa_message_id',
                'error',
                'ip_address',
            ]))));
        } catch (Throwable $e) {
            Log::error('WaApiUsageService: gagal mencatat riwayat request WA API', [
                'api_key_id' => $apiKey->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Jumlah request per status untuk satu API key sejak $since (null =
     * sepanjang waktu). Satu query GROUP BY, memakai index
     * (wa_api_key_id, status, created_at).
     *
     * @return array{sent: int, blocked_package: int, blocked_quota: int, failed: int, total: int}
     */
    public function countsFor(WaApiKey $apiKey, ?Carbon $since = null): array
    {
        $rows = WaApiRequestLog::where('wa_api_key_id', $apiKey->id)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];

        foreach (array_keys(WaApiRequestLog::statusLabels()) as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Ringkasan lengkap untuk halaman riwayat & endpoint GET /usage:
     * info paket aktif + kuota broadcast_send (dipakai bersama semua
     * jalur kirim WA company ini, bukan cuma API) + jumlah request API
     * key ini per jendela waktu.
     */
    public function summary(WaApiKey $apiKey): array
    {
        $company = $apiKey->company;
        $voucher = $company ? $this->packageLimits->resolveActiveVoucher($company) : null;
        $periodStart = $voucher?->valid_from;

        return [
            'package' => $this->packageInfo($company, $voucher),
            'quota' => $company ? $this->quotaInfo($company) : null,
            'api_key' => [
                'today' => $this->countsFor($apiKey, now()->startOfDay()),
                'this_month' => $this->countsFor($apiKey, now()->startOfMonth()),
                'current_package_period' => $periodStart ? $this->countsFor($apiKey, Carbon::parse($periodStart)) : null,
                'all_time' => $this->countsFor($apiKey),
            ],
        ];
    }

    /**
     * Paket aktif + kuota broadcast_send satu company — dipakai halaman
     * Superadmin > Pemakaian WA API (App\Http\Controllers\Superadmin\
     * WaApiUsageController), yang melihat per company, bukan per API key.
     *
     * @return array{package: ?array, quota: array}
     */
    public function companySummary(Company $company): array
    {
        $voucher = $this->packageLimits->resolveActiveVoucher($company);

        return [
            'package' => $this->packageInfo($company, $voucher),
            'quota' => $this->quotaInfo($company),
        ];
    }

    private function packageInfo(?Company $company, $voucher): ?array
    {
        if (! $company || ! $voucher) {
            return null;
        }

        return [
            'name' => $voucher->package?->name,
            'valid_from' => $voucher->valid_from?->toIso8601String(),
            'valid_until' => $voucher->valid_until?->toIso8601String(),
        ];
    }

    /**
     * Kuota broadcast_send paket aktif. 'unlimited' true kalau paket
     * tidak membatasi metric ini (atau superadmin belum mengisi
     * limitnya) — sama persis dengan perilaku fail-open
     * PackageLimitService::reserve().
     */
    private function quotaInfo(Company $company): array
    {
        $packageLimit = $this->packageLimits->limitFor($company, 'broadcast_send');

        if (! $packageLimit) {
            return [
                'metric' => 'broadcast_send',
                'unlimited' => true,
                'max' => null,
                'used' => null,
                'remaining' => null,
            ];
        }

        $remaining = (int) $this->packageLimits->remaining($company, 'broadcast_send');

        return [
            'metric' => 'broadcast_send',
            'unlimited' => false,
            'max' => $packageLimit->max_value,
            'used' => max(0, $packageLimit->max_value - $remaining),
            'remaining' => $remaining,
        ];
    }
}
