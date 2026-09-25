<?php

namespace App\Services;

use App\Exceptions\PackageLimitExceededException;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\CompanyLimitUsage;
use App\Models\JadwalReminderSetting;
use App\Models\LimitMetric;
use App\Models\Package;
use App\Models\PackageLimit;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Notifications\PackageLimitExhaustedNotification;
use App\Services\Chat\DeviceDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang menjawab "apakah company/branch ini masih boleh
 * melakukan X?" untuk App\Models\LimitMetric mana pun di App\Models\Package
 * mana pun.
 *
 * PAKET BERLAKU PER BRANCH (alur Company -> Branch -> Paket, 25 September
 * 2026). Hampir semua method menerima ?BranchOffice $branch:
 *   - diisi  -> hanya voucher/paket milik branch itu yang dihitung, dan
 *               counter kuota (CompanyLimitUsage) juga milik branch itu.
 *   - null   -> perilaku lama tingkat company: voucher aktif milik branch
 *               mana pun, counter kuota tingkat company. Hanya dipakai
 *               untuk pengecekan kasar ("company ini masih pelanggan?")
 *               di tempat yang memang tidak tahu branch-nya; pengecekan
 *               pengiriman WA yang sebenarnya selalu per branch device
 *               (lihat App\Services\Chat\InboxService::guardPackageLimit()).
 *
 * Dua cara metric diukur, per LimitMetric::metric_type:
 *   - 'consumable' -- counter berjalan (CompanyLimitUsage.used_value) per
 *     PERIODE BULANAN: max_value paket = jatah per bulan, dihitung dari
 *     tanggal paket aktif (voucher.valid_from), bukan tanggal 1. Tiap
 *     bulan counter baru mulai dari 0; sisa bulan lalu TIDAK dibawa
 *     (tidak akumulasi). Paket 3 hari / 1 bulan cuma punya satu periode.
 *     Lihat currentPeriod().
 *   - 'stock' -- dihitung live dari data sebenarnya lewat callback dari
 *     pemanggil (mis. jumlah kontak), jadi tidak pernah melenceng.
 */
class PackageLimitService
{
    public function __construct(protected DeviceDirectory $devices)
    {
    }

    /**
     * Voucher yang sedang berlaku untuk company (dan branch, kalau diisi).
     * Yang terbaru menang kalau ada lebih dari satu.
     */
    public function resolveActiveVoucher(Company $company, ?BranchOffice $branch = null): ?Voucher
    {
        return $this->activeVouchers($company, $branch)
            ->latest('valid_from')
            ->first();
    }

    public function activePackage(Company $company, ?BranchOffice $branch = null): ?Package
    {
        return $this->resolveActiveVoucher($company, $branch)?->package;
    }

    public function activeSubscription(Company $company, ?BranchOffice $branch = null): ?Subscription
    {
        return $this->resolveActiveVoucher($company, $branch)?->subscription;
    }

    /**
     * Apakah company/branch punya paket aktif yang MENCAKUP salah satu
     * layanan bernama $categoryNames (mis. JadwalReminderSetting::
     * CHAT_CATEGORY_NAMES). Cakupan dibaca dari pivot paket (paket bisa
     * multi-layanan) maupun category utamanya -- lihat
     * Package::scopeCoveringCategoryNames().
     *
     * @param  array<int, string>  $categoryNames
     */
    public function hasActiveCategoryPackage(Company $company, array $categoryNames, ?BranchOffice $branch = null): bool
    {
        return $this->activeVouchers($company, $branch)
            ->whereHas('package', fn (Builder $q) => $q->coveringCategoryNames($categoryNames))
            ->exists();
    }

    /**
     * Melempar PackageLimitExceededException ('active_package') kalau
     * company/branch tidak punya paket aktif -- atau, kalau $categoryNames
     * diisi, tidak punya paket aktif yang mencakup layanan itu.
     *
     * Kebalikan dari assertWithinLimit()/reserve() yang fail-OPEN saat
     * tidak ada paket: method ini menjawab "masih pelanggan atau tidak",
     * bukan "kuota metric ini sudah habis atau belum". Dipanggil job/
     * command (yang tidak lewat middleware HTTP) sebelum mengirim WA.
     *
     * @param  array<int, string>|null  $categoryNames
     */
    public function requireActivePackage(Company $company, ?BranchOffice $branch = null, ?array $categoryNames = null): void
    {
        $active = $categoryNames === null
            ? $this->activePackage($company, $branch) !== null
            : $this->hasActiveCategoryPackage($company, $categoryNames, $branch);

        if ($active) {
            return;
        }

        $this->recordBreach();

        throw new PackageLimitExceededException(
            $branch
                ? "Branch {$branch->name} tidak memiliki paket aktif untuk layanan ini. Perpanjang atau beli paket untuk branch tersebut."
                : 'Masa aktif package perusahaan ini sudah habis. Redeem voucher atau beli package baru untuk melanjutkan pengiriman WhatsApp.',
            'active_package'
        );
    }

    /**
     * Branch pemilik sebuah device WhatsApp (wa_devices.branch_office_id,
     * di-cache singkat oleh DeviceDirectory). Null kalau device belum
     * terpasang ke branch mana pun.
     */
    public function branchForDevice(?string $deviceId): ?BranchOffice
    {
        if (! $deviceId) {
            return null;
        }

        $branchId = $this->devices->scopeFor($deviceId)['branch_office_id'] ?? null;

        return $branchId ? BranchOffice::find($branchId) : null;
    }

    /**
     * Company pemilik sebuah device WhatsApp, atau null.
     */
    public function companyForDevice(?string $deviceId): ?Company
    {
        $companyId = $deviceId ? $this->devices->companyFor($deviceId) : null;

        return $companyId ? Company::find($companyId) : null;
    }

    /**
     * Shortcut untuk pengecekan pengiriman WA: device ini boleh mengirim
     * kalau branch-nya punya paket aktif yang mencakup layanan Chat.
     */
    public function deviceHasActiveChatPackage(Company $company, ?string $deviceId): bool
    {
        $branch = $this->branchForDevice($deviceId);

        return $branch !== null
            && $this->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES, $branch);
    }

    /**
     * CLAUDE.md checklist #10 -- satu titik penghitung setiap
     * PackageLimitExceededException (lihat PlatformAlertService). Di-resolve
     * lewat app() karena PlatformAlertService bergantung ke InboxService
     * yang bergantung ke class ini (circular kalau lewat constructor).
     */
    private function recordBreach(): void
    {
        app(\App\Services\PlatformAlertService::class)->recordPackageLimitBreach();
    }

    /**
     * Baris katalog metric untuk sebuah key, memprioritaskan yang terikat
     * ke category tertentu lalu fallback ke metric global (tanpa category).
     */
    public function metricByKey(string $key, ?string $categoryApplicationId = null): ?LimitMetric
    {
        $query = LimitMetric::where('key', $key)->where('status', 'active');

        if ($categoryApplicationId) {
            $scoped = (clone $query)->where('category_application_id', $categoryApplicationId)->first();

            if ($scoped) {
                return $scoped;
            }
        }

        return $query->whereNull('category_application_id')->first();
    }

    /**
     * Limit (max_value) yang dipasang paket aktif company/branch untuk
     * metric ini, atau null (= unlimited: tidak ada paket aktif, atau paket
     * tidak membatasi metric ini).
     */
    public function limitFor(Company $company, string $metricKey, ?BranchOffice $branch = null): ?PackageLimit
    {
        $package = $this->activePackage($company, $branch);

        return $package ? $this->packageLimitFor($package, $metricKey) : null;
    }

    /**
     * Sisa jatah metric ini -- null berarti unlimited. Untuk metric 'stock'
     * $liveCountResolver wajib diisi (mengembalikan jumlah sekarang).
     */
    public function remaining(Company $company, string $metricKey, ?BranchOffice $branch = null, ?callable $liveCountResolver = null): ?int
    {
        [$packageLimit, $voucher] = $this->limitAndVoucher($company, $metricKey, $branch);

        if (! $packageLimit) {
            return null;
        }

        if ($packageLimit->limitMetric->isStock()) {
            $used = $liveCountResolver ? (int) $liveCountResolver() : 0;

            return max(0, $packageLimit->max_value - $used);
        }

        $usage = $this->usageRow($company, $packageLimit->limitMetric, $branch, $voucher);

        return max(0, $packageLimit->max_value - $usage->used_value);
    }

    /**
     * Melempar PackageLimitExceededException kalau tindakan ini ($amount
     * unit $metricKey) melampaui limit paket aktif. Fail-open (diizinkan)
     * kalau tidak ada paket aktif atau metric-nya tidak dibatasi.
     */
    public function assertWithinLimit(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null, ?callable $liveCountResolver = null): void
    {
        [$packageLimit, $voucher] = $this->limitAndVoucher($company, $metricKey, $branch);

        if (! $packageLimit) {
            return;
        }

        $metric = $packageLimit->limitMetric;

        if ($metric->isStock()) {
            $used = $liveCountResolver ? (int) $liveCountResolver() : 0;

            if ($used + $amount > $packageLimit->max_value) {
                $this->recordBreach();

                throw new PackageLimitExceededException(
                    "Batas {$metric->name} paket Anda sudah tercapai ({$packageLimit->max_value} {$metric->unit}). Hapus data lama atau upgrade paket untuk menambah kapasitas.",
                    $metricKey
                );
            }

            return;
        }

        $usage = $this->usageRow($company, $metric, $branch, $voucher);

        if ($usage->used_value + $amount > $packageLimit->max_value) {
            $this->notifyExhausted($company, $metric, $usage, $packageLimit->max_value);
            $this->recordBreach();

            throw $this->quotaExhausted($metric, $usage, $packageLimit);
        }
    }

    /**
     * Catat pemakaian SETELAH tindakannya benar-benar terjadi. Tidak
     * melempar kalau melewati limit (pakai reserve() untuk itu).
     */
    public function consume(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        [$packageLimit, $voucher] = $this->limitAndVoucher($company, $metricKey, $branch);

        if (! $packageLimit || ! $packageLimit->limitMetric->isConsumable()) {
            return;
        }

        DB::transaction(function () use ($company, $packageLimit, $branch, $voucher, $amount) {
            $this->lockOrCreateUsage($company, $packageLimit->limitMetric, $branch, $voucher)
                ->increment('used_value', $amount);
        });
    }

    /**
     * Cek + potong kuota consumable dalam SATU transaksi terkunci
     * (lockForUpdate), supaya dua pengiriman bersamaan tidak sama-sama
     * lolos di "sisa 1". Pasangan release() kalau pengirimannya gagal.
     */
    public function reserve(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        [$packageLimit, $voucher] = $this->limitAndVoucher($company, $metricKey, $branch);

        if (! $packageLimit || ! $packageLimit->limitMetric->isConsumable()) {
            return;
        }

        DB::transaction(function () use ($company, $packageLimit, $branch, $voucher, $amount) {
            $metric = $packageLimit->limitMetric;
            $usage = $this->lockOrCreateUsage($company, $metric, $branch, $voucher);

            if ($usage->used_value + $amount > $packageLimit->max_value) {
                $this->notifyExhausted($company, $metric, $usage, $packageLimit->max_value);
                $this->recordBreach();

                throw $this->quotaExhausted($metric, $usage, $packageLimit);
            }

            $usage->increment('used_value', $amount);
        });
    }

    /**
     * Kembalikan reservasi reserve() saat pengiriman yang menyusul gagal.
     * Aman dipanggil walau reserve() tidak memotong apa pun; tidak pernah
     * membuat counter negatif.
     */
    public function release(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        [$packageLimit, $voucher] = $this->limitAndVoucher($company, $metricKey, $branch);

        if (! $packageLimit || ! $packageLimit->limitMetric->isConsumable()) {
            return;
        }

        DB::transaction(function () use ($company, $packageLimit, $branch, $voucher, $amount) {
            $usage = $this->usageQuery($company, $packageLimit->limitMetric, $branch, $voucher, $this->currentPeriod($voucher)[0])
                ->lockForUpdate()
                ->first();

            if ($usage) {
                $usage->update(['used_value' => max(0, $usage->used_value - $amount)]);
            }
        });
    }

    /**
     * Kirim notifikasi "kuota habis" ke owner company, maksimal sekali per
     * periode bulanan (notified_at ada di baris counter periode itu).
     */
    public function notifyExhausted(Company $company, LimitMetric $metric, CompanyLimitUsage $usage, int $maxValue): void
    {
        if ($usage->notified_at !== null) {
            return;
        }

        $company->user?->notify(new PackageLimitExhaustedNotification($metric->name, $metric->unit, $maxValue));

        $usage->forceFill(['notified_at' => now()])->save();
    }

    /**
     * Semua limit paket aktif company/branch beserta pemakaiannya -- untuk
     * halaman pemakaian paket. $liveCountResolvers: [metric_key => callable]
     * untuk metric 'stock'; metric stock tanpa resolver tampil null
     * ("tidak diketahui"), bukan 0 yang menyesatkan.
     *
     * @param  array<string, callable>  $liveCountResolvers
     * @return array<int, array{metric: LimitMetric, max_value: int, used: ?int, remaining: ?int, period_start: ?\Illuminate\Support\Carbon, period_end: ?\Illuminate\Support\Carbon}>
     */
    public function usageReport(Company $company, ?BranchOffice $branch = null, array $liveCountResolvers = []): array
    {
        $voucher = $this->resolveActiveVoucher($company, $branch);

        if (! $voucher?->package) {
            return [];
        }

        $limits = PackageLimit::with('limitMetric')->where('package_id', $voucher->package_id)->get();

        return $limits->map(function (PackageLimit $packageLimit) use ($company, $branch, $voucher, $liveCountResolvers) {
            $metric = $packageLimit->limitMetric;

            if ($metric->isStock()) {
                $resolver = $liveCountResolvers[$metric->key] ?? null;
                $used = $resolver ? $resolver() : null;
                $used = $used !== null ? (int) $used : null;

                return [
                    'metric' => $metric,
                    'max_value' => $packageLimit->max_value,
                    'used' => $used,
                    'remaining' => $used !== null ? max(0, $packageLimit->max_value - $used) : null,
                    'period_start' => null,
                    'period_end' => null,
                ];
            }

            $usage = $this->usageRow($company, $metric, $branch, $voucher);

            return [
                'metric' => $metric,
                'max_value' => $packageLimit->max_value,
                'used' => $usage->used_value,
                'remaining' => max(0, $packageLimit->max_value - $usage->used_value),
                'period_start' => $usage->period_start,
                'period_end' => $usage->period_end,
            ];
        })->all();
    }

    /**
     * Query dasar voucher aktif company, dipersempit ke satu branch kalau
     * $branch diisi.
     */
    protected function activeVouchers(Company $company, ?BranchOffice $branch): Builder
    {
        return Voucher::query()
            ->currentlyActive()
            ->where('company_id', $company->id)
            ->when($branch, fn (Builder $q) => $q->where('branch_office_id', $branch->id));
    }

    /**
     * PackageLimit paket ini untuk metric ber-key $metricKey. Dicari lewat
     * limit yang benar-benar dipasang di paket (bukan lewat category
     * utama), jadi paket multi-layanan dan metric dari category mana pun
     * tetap terbaca selama superadmin memasangnya di paket itu.
     */
    protected function packageLimitFor(Package $package, string $metricKey): ?PackageLimit
    {
        return PackageLimit::with('limitMetric')
            ->where('package_id', $package->id)
            ->whereHas('limitMetric', fn (Builder $q) => $q->where('key', $metricKey)->where('status', 'active'))
            ->first();
    }

    /**
     * @return array{0: ?PackageLimit, 1: ?Voucher}
     */
    protected function limitAndVoucher(Company $company, string $metricKey, ?BranchOffice $branch): array
    {
        $voucher = $this->resolveActiveVoucher($company, $branch);

        if (! $voucher?->package) {
            return [null, null];
        }

        return [$this->packageLimitFor($voucher->package, $metricKey), $voucher];
    }

    /**
     * Periode kuota bulanan yang sedang berjalan untuk voucher aktif:
     * [awal, akhir). Bulan ke-n = valid_from + n bulan (addMonthsNoOverflow
     * dari valid_from asli, jadi 31 Jan -> 28 Feb -> 31 Mar tidak
     * bergeser). Akhir periode terakhir dipotong di valid_until.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function currentPeriod(Voucher $voucher): array
    {
        $from = Carbon::parse($voucher->valid_from);
        $now = now();
        $startOf = fn (int $month) => $from->copy()->addMonthsNoOverflow($month);

        $month = max(0, (int) floor($from->diffInMonths($now)));

        while ($month > 0 && $startOf($month)->gt($now)) {
            $month--;
        }

        while ($startOf($month + 1)->lte($now)) {
            $month++;
        }

        $end = $startOf($month + 1);

        if ($voucher->valid_until && $end->gt($voucher->valid_until)) {
            $end = Carbon::parse($voucher->valid_until);
        }

        return [$startOf($month), $end];
    }

    protected function quotaExhausted(LimitMetric $metric, CompanyLimitUsage $usage, PackageLimit $packageLimit): PackageLimitExceededException
    {
        return new PackageLimitExceededException(
            "Kuota {$metric->name} paket Anda bulan ini sudah habis ({$usage->used_value}/{$packageLimit->max_value} {$metric->unit}). Kuota terisi lagi pada ".$usage->period_end?->format('d M Y H:i').'.',
            $metric->key
        );
    }

    /**
     * Counter consumable satu (company, branch, metric, subscription,
     * periode bulanan), dibuat saat pertama dipakai.
     */
    protected function usageRow(Company $company, LimitMetric $metric, ?BranchOffice $branch, Voucher $voucher): CompanyLimitUsage
    {
        return DB::transaction(fn () => $this->lockOrCreateUsage($company, $metric, $branch, $voucher));
    }

    protected function usageQuery(Company $company, LimitMetric $metric, ?BranchOffice $branch, Voucher $voucher, Carbon $periodStart): Builder
    {
        return CompanyLimitUsage::where('company_id', $company->id)
            ->where('branch_office_id', $branch?->id)
            ->where('limit_metric_id', $metric->id)
            ->where('subscription_id', $voucher->subscription_id)
            ->where('period_start', $periodStart);
    }

    /**
     * Ambil (dengan row lock) atau buat counter -- WAJIB dipanggil di
     * dalam DB::transaction(). lockForUpdate() tidak bisa mengunci baris
     * yang belum ada, jadi balapan INSERT pertama ditangkap lewat unique
     * constraint usage_key (lihat migration company_limit_usages).
     */
    protected function lockOrCreateUsage(Company $company, LimitMetric $metric, ?BranchOffice $branch, Voucher $voucher): CompanyLimitUsage
    {
        [$periodStart, $periodEnd] = $this->currentPeriod($voucher);

        $find = fn () => $this->usageQuery($company, $metric, $branch, $voucher, $periodStart)->lockForUpdate()->first();

        if ($row = $find()) {
            return $row;
        }

        try {
            return CompanyLimitUsage::create([
                'company_id' => $company->id,
                'branch_office_id' => $branch?->id,
                'limit_metric_id' => $metric->id,
                'subscription_id' => $voucher->subscription_id,
                'used_value' => 0,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
        } catch (QueryException $e) {
            // Kalah balapan baris pertama -- transaksi lain baru saja
            // membuatnya. Ambil & kunci milik mereka.
            if ($row = $find()) {
                return $row;
            }

            throw $e;
        }
    }
}
