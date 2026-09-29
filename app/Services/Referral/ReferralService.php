<?php

namespace App\Services\Referral;

use App\Models\ReferralCode;
use App\Models\ReferralCodeUsage;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat aturan referral/reseller:
 *
 * - Diskon Rupiah untuk customer HANYA di pembelian pertama (saat customer
 *   pertama kali terhubung ke pemilik kode), dan diambil dari komisi pemilik
 *   kode -- jadi diskon tidak pernah melebihi komisinya.
 * - Komisi berulang setiap customer membayar/perpanjang (berhenti sendiri
 *   kalau langganan berhenti, karena tidak ada pembayaran lagi).
 * - Komisi pembelian pertama tertahan selama masa komplain (hold days),
 *   pembelian berikutnya langsung masuk wallet. Yang tertahan dicairkan
 *   oleh command referral:release-commissions, atau dibatalkan superadmin.
 * - Semua angka diatur superadmin (Setting), bisa di-override per kode.
 */
class ReferralService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CANCELLED = 'cancelled';

    /** Pengaturan superadmin => nilai default. */
    public const SETTINGS = [
        'referral_commission_percent' => 20,
        'referral_buyer_discount' => 0,
        'referral_max_buyer_discount' => 50000,
        'referral_hold_days' => 30,
    ];

    public static function setting(string $key): float
    {
        return (float) Setting::get($key, self::SETTINGS[$key]);
    }

    /** @return array<string, float> */
    public static function settings(): array
    {
        return collect(self::SETTINGS)->mapWithKeys(fn ($default, $key) => [$key => self::setting($key)])->all();
    }

    public function commissionPercent(ReferralCode $code): float
    {
        return (float) ($code->percentage ?? self::setting('referral_commission_percent'));
    }

    public function buyerDiscount(ReferralCode $code): float
    {
        $amount = (float) ($code->buyer_discount_amount ?? self::setting('referral_buyer_discount'));

        return max(0, min($amount, self::setting('referral_max_buyer_discount')));
    }

    /**
     * @return array{valid: bool, message: string, data?: ReferralCode}
     */
    public function validate(string $code, User $buyer): array
    {
        if ($code === '') {
            return ['valid' => false, 'message' => 'Masukkan kode referral.'];
        }

        $referralCode = ReferralCode::with('user')->where('code', $code)->first();

        if (! $referralCode) {
            return ['valid' => false, 'message' => 'Kode referral tidak ditemukan.'];
        }

        if ($referralCode->status !== 'active') {
            return ['valid' => false, 'message' => 'Kode referral tidak aktif / diblokir.'];
        }

        if ($referralCode->user_id === $buyer->id || $this->samePerson($referralCode->user, $buyer)) {
            return ['valid' => false, 'message' => 'Kode referral tidak bisa dipakai oleh pemilik kode sendiri.'];
        }

        // Customer terhubung permanen ke satu pemilik kode (users.referrer_id).
        if ($buyer->referrer_id && $buyer->referrer_id !== $referralCode->user_id) {
            return ['valid' => false, 'message' => 'Anda sudah terhubung dengan kode referral lain sebelumnya dan tidak bisa menggantinya.'];
        }

        return ['valid' => true, 'message' => 'Kode referral valid.', 'data' => $referralCode];
    }

    /**
     * Rincian referral untuk satu pembelian. $base = harga setelah diskon promo.
     *
     * @return array{percent: float, discount: float, commission: float}
     */
    public function quote(ReferralCode $code, float $base, bool $firstPurchase): array
    {
        $percent = $this->commissionPercent($code);
        $gross = round(max(0, $base) * $percent / 100, 2);
        $discount = $firstPurchase ? round(min($this->buyerDiscount($code), $gross), 2) : 0.0;

        return ['percent' => $percent, 'discount' => $discount, 'commission' => round($gross - $discount, 2)];
    }

    /**
     * Catat komisi satu pembelian (dipanggil di dalam transaksi checkout).
     * Pembelian pertama tertahan selama masa komplain; selanjutnya langsung cair.
     */
    public function record(ReferralCode $code, User $buyer, Subscription $subscription, array $quote, bool $firstPurchase): ReferralCodeUsage
    {
        $eligible = $code->status === 'active' && $buyer->status === 'active' && $quote['commission'] > 0;
        $holdDays = (int) self::setting('referral_hold_days');
        $hold = $eligible && $firstPurchase && $holdDays > 0;

        $usage = ReferralCodeUsage::create([
            'referral_code_id' => $code->id,
            'used_by_user_id' => $buyer->id,
            'subscription_id' => $subscription->id,
            'discount_percent' => $quote['percent'],
            'buyer_discount_amount' => $quote['discount'],
            'commission_amount' => $eligible ? $quote['commission'] : 0,
            'status' => $eligible ? self::STATUS_PENDING : self::STATUS_AVAILABLE,
            'available_at' => $hold ? now()->addDays($holdDays) : now(),
        ]);

        if ($eligible && ! $hold) {
            $this->credit($usage);
        }

        return $usage;
    }

    /**
     * Total komisi per status untuk query ReferralCodeUsage tertentu.
     *
     * @return array{available: float, pending: float}
     */
    public function totals(Builder $query): array
    {
        $sums = $query->selectRaw('status, SUM(commission_amount) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'available' => (float) ($sums[self::STATUS_AVAILABLE] ?? 0),
            'pending' => (float) ($sums[self::STATUS_PENDING] ?? 0),
        ];
    }

    /** Cairkan semua komisi yang masa tahannya sudah lewat. */
    public function releaseDue(): int
    {
        return ReferralCodeUsage::where('status', self::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->pluck('id')
            ->filter(fn (string $id) => DB::transaction(function () use ($id) {
                $usage = ReferralCodeUsage::whereKey($id)->lockForUpdate()->first();

                return $usage
                    && $usage->status === self::STATUS_PENDING
                    && ! $usage->available_at?->isFuture()
                    && $this->credit($usage);
            }))
            ->count();
    }

    /** Batalkan komisi yang masih tertahan (mis. ada komplain/refund). */
    public function cancel(string $usageId, string $reason): bool
    {
        return DB::transaction(function () use ($usageId, $reason) {
            $usage = ReferralCodeUsage::whereKey($usageId)->lockForUpdate()->first();

            if (! $usage || $usage->status !== self::STATUS_PENDING) {
                return false;
            }

            return $usage->update([
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
        });
    }

    /**
     * Masukkan komisi ke wallet pemilik kode. Tetap tertahan (dicoba lagi oleh
     * command) kalau kode sedang diblokir atau wallet belum ada.
     */
    private function credit(ReferralCodeUsage $usage): bool
    {
        $code = $usage->referralCode()->with('user.wallet')->first();
        $wallet = $code?->user?->wallet;

        if (! $wallet || $code->status !== 'active') {
            return false;
        }

        WalletLedgerService::credit(
            $wallet,
            (float) $usage->commission_amount,
            Subscription::class,
            $usage->subscription_id,
            'Komisi referral dari pembelian '.($usage->usedBy?->name ?? 'customer'),
            $usage->used_by_user_id,
            'REFERRAL_COMMISSION'
        );

        return $usage->update(['status' => self::STATUS_AVAILABLE, 'credited_at' => now()]);
    }

    /** Akun berbeda tapi nomor HP sama dianggap orang yang sama. */
    private function samePerson(?User $owner, User $buyer): bool
    {
        $normalize = function (?string $phone): string {
            $digits = preg_replace('/\D/', '', (string) $phone);

            return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
        };

        $ownerPhone = $normalize($owner?->handphone);

        return $ownerPhone !== '' && $ownerPhone === $normalize($buyer->handphone);
    }
}
