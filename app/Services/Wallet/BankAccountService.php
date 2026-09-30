<?php

namespace App\Services\Wallet;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BankAccountNotification;
use App\Services\Payment\DuitkuDisbursementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya tempat aturan rekening pencairan:
 *
 * - Nama pemilik rekening SELALU dari bank (inquiry Duitku), tidak pernah
 *   dari ketikan user.
 * - Rekening pertama menjadi patokan nama (users.verified_bank_name).
 *   Rekening berikutnya dengan nama sama langsung disetujui; nama beda
 *   menunggu pemeriksaan superadmin.
 * - Rekening baru baru bisa dipakai setelah masa tunggu (hold hours);
 *   selama itu tarik saldo tetap ke rekening lama.
 * - Ganti rekening dibatasi sekali per N hari (change days).
 * - Baris rekening tidak pernah diubah isinya/dihapus: tabelnya sekaligus
 *   riwayat. Setiap aksi penting juga dicatat di AuditLog.
 */
class BankAccountService
{
    /** Pengaturan superadmin => nilai default. */
    public const SETTINGS = [
        'bank_account_hold_hours' => 24,
        'bank_account_change_days' => 30,
    ];

    private const CHECK_TTL_MINUTES = 10;

    private const INQUIRY_AMOUNT = 10000;

    public static function setting(string $key): int
    {
        return (int) Setting::get($key, self::SETTINGS[$key]);
    }

    /** @return array<string, int> */
    public static function settings(): array
    {
        return collect(self::SETTINGS)->mapWithKeys(fn ($default, $key) => [$key => self::setting($key)])->all();
    }

    public static function normalizeNumber(string $number): string
    {
        return preg_replace('/\D/', '', $number);
    }

    /** Sidik jari nomor rekening untuk deteksi rekening yang dipakai banyak akun. */
    public static function hash(string $bankCode, string $number): string
    {
        return hash_hmac('sha256', $bankCode.'|'.$number, (string) config('app.key'));
    }

    /** Huruf besar/kecil, titik, koma, dan spasi ganda diabaikan. */
    public static function sameName(?string $a, ?string $b): bool
    {
        $normalize = fn (?string $name) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z]/', ' ', Str::upper((string) $name))));

        return $normalize($a) !== '' && $normalize($a) === $normalize($b);
    }

    /** @return array<int, array{bankCode: string, bankName: string}> */
    public function banks(): array
    {
        if ($banks = Cache::get('duitku:disbursement-banks')) {
            return $banks;
        }

        try {
            $banks = DuitkuDisbursementService::make()->listBanks();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        if ($banks) {
            Cache::put('duitku:disbursement-banks', $banks, 3600);
        }

        return $banks;
    }

    /** Rekening yang saat ini dipakai tarik saldo. */
    public function current(User $user): ?BankAccount
    {
        return $user->bankAccounts()->usable()->latest('active_at')->first();
    }

    /** Pengajuan terakhir kalau belum/tidak jadi rekening aktif (diperiksa, segera aktif, ditolak). */
    public function latestSubmission(User $user): ?BankAccount
    {
        $latest = $user->bankAccounts()->latest()->first();

        return $latest && in_array($latest->state(), ['review', 'scheduled', 'rejected'], true) ? $latest : null;
    }

    /** Alasan user belum boleh mendaftarkan rekening baru; null = boleh. */
    public function blockedReason(User $user): ?string
    {
        if ($user->bankAccounts()->where('status', BankAccount::STATUS_PENDING_REVIEW)->exists()) {
            return 'Rekening yang Anda ajukan sebelumnya masih diperiksa tim kami. Tunggu hasilnya dulu ya.';
        }

        $last = $user->bankAccounts()->where('status', BankAccount::STATUS_APPROVED)->latest()->first();
        $until = ($last?->reviewed_at ?? $last?->created_at)?->copy()->addDays(self::setting('bank_account_change_days'));

        return $until?->isFuture()
            ? 'Anda baru bisa ganti rekening lagi mulai '.$until->translatedFormat('d M Y').'. Butuh lebih cepat? Hubungi tim kami.'
            : null;
    }

    /**
     * Cek rekening ke bank (tidak menyimpan apa pun).
     *
     * @return array{bank_code: string, bank_name: string, account_number: string, account_name: string, matched: bool}
     */
    public function inquire(User $user, string $bankCode, string $accountNumber): array
    {
        if ($reason = $this->blockedReason($user)) {
            throw new RuntimeException($reason);
        }

        $bank = collect($this->banks())->firstWhere('bankCode', $bankCode);

        if (! $bank) {
            throw new RuntimeException('Bank tidak ditemukan. Silakan pilih bank dari daftar.');
        }

        try {
            $result = DuitkuDisbursementService::make()->inquiry($bankCode, $accountNumber, self::INQUIRY_AMOUNT, 'Verifikasi rekening');
        } catch (Throwable $e) {
            Log::warning('bank-account: inquiry gagal', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            throw new RuntimeException('Kami belum bisa mengecek rekening ke bank saat ini. Silakan coba beberapa menit lagi.');
        }

        $name = trim((string) ($result['accountName'] ?? ''));

        if (($result['responseCode'] ?? null) !== '00' || $name === '') {
            throw new RuntimeException('Nomor rekening tidak ditemukan di bank tersebut. Coba periksa lagi nomor dan banknya.');
        }

        return [
            'bank_code' => $bankCode,
            'bank_name' => (string) $bank['bankName'],
            'account_number' => $accountNumber,
            'account_name' => Str::upper($name),
            'matched' => $user->verified_bank_name === null || self::sameName($user->verified_bank_name, $name),
        ];
    }

    /** Hasil cek disimpan di server (terenkripsi, 10 menit) -- langkah konfirmasi tidak percaya data dari browser. */
    public function rememberCheck(User $user, array $check): void
    {
        Cache::put($this->checkKey($user), Crypt::encrypt($check), now()->addMinutes(self::CHECK_TTL_MINUTES));
    }

    public function pendingCheck(User $user): ?array
    {
        try {
            $value = Cache::get($this->checkKey($user));

            return $value ? Crypt::decrypt($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function forgetCheck(User $user): void
    {
        Cache::forget($this->checkKey($user));
    }

    /** Simpan rekening hasil cek yang sudah dikonfirmasi user. */
    public function register(User $user, array $check): BankAccount
    {
        $account = DB::transaction(function () use ($user, $check) {
            // Kunci baris user supaya dua submit bersamaan tidak lolos batas ganti rekening.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($reason = $this->blockedReason($user)) {
                throw new RuntimeException($reason);
            }

            $first = $user->verified_bank_name === null;
            $matched = $first || self::sameName($user->verified_bank_name, $check['account_name']);

            $account = $user->bankAccounts()->create([
                'bank_code' => $check['bank_code'],
                'bank_name' => $check['bank_name'],
                'account_number' => $check['account_number'],
                'account_number_hash' => self::hash($check['bank_code'], $check['account_number']),
                'account_last4' => substr($check['account_number'], -4),
                'account_name' => $check['account_name'],
                'name_matched' => $matched,
                'status' => $matched ? BankAccount::STATUS_APPROVED : BankAccount::STATUS_PENDING_REVIEW,
                'active_at' => $matched ? now()->addHours(self::setting('bank_account_hold_hours')) : null,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);

            if ($first) {
                $user->forceFill(['verified_bank_name' => $check['account_name']])->save();
            }

            if ($matched) {
                $this->replacePrevious($account);
            }

            $this->audit('bank_account.register', $account, ['first' => $first, 'name_matched' => $matched], $user);

            return $account;
        });

        $this->notify($account, 'registered');

        return $account;
    }

    public function approve(string $accountId, User $reviewer): BankAccount
    {
        $account = $this->review($accountId, $reviewer, [
            'status' => BankAccount::STATUS_APPROVED,
            'active_at' => now(),
        ]);

        $this->notify($account, 'approved');

        return $account;
    }

    public function reject(string $accountId, User $reviewer, string $reason): BankAccount
    {
        $account = $this->review($accountId, $reviewer, [
            'status' => BankAccount::STATUS_REJECTED,
            'review_note' => $reason,
        ]);

        $this->notify($account, 'rejected');

        return $account;
    }

    /** Rekening berikutnya yang didaftarkan user ini akan menjadi patokan nama baru. */
    public function resetVerifiedName(User $user, User $reviewer, string $reason): void
    {
        $this->ensureNotOwn($user->id, $reviewer);

        $before = $user->verified_bank_name;
        $user->forceFill(['verified_bank_name' => null])->save();

        AuditLog::record('bank_account.reset_name', User::class, $user->id, ['verified_bank_name' => $before], ['reason' => $reason]);
    }

    /** Nomor lengkap untuk superadmin -- setiap pembukaan dicatat. */
    public function reveal(BankAccount $account): string
    {
        $this->audit('bank_account.reveal', $account);

        return $account->account_number;
    }

    /**
     * Berapa akun LAIN yang memakai rekening yang sama, per hash.
     *
     * @param  iterable<BankAccount>  $accounts
     * @return array<string, int>
     */
    public function sharedCounts(iterable $accounts): array
    {
        $hashes = collect($accounts)->pluck('account_number_hash')->unique()->values();

        if ($hashes->isEmpty()) {
            return [];
        }

        return BankAccount::whereIn('account_number_hash', $hashes)
            ->groupBy('account_number_hash')
            ->selectRaw('account_number_hash, COUNT(DISTINCT user_id) - 1 as others')
            ->pluck('others', 'account_number_hash')
            ->map(fn ($others) => (int) $others)
            ->all();
    }

    private function review(string $accountId, User $reviewer, array $changes): BankAccount
    {
        return DB::transaction(function () use ($accountId, $reviewer, $changes) {
            $account = BankAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();

            if ($account->status !== BankAccount::STATUS_PENDING_REVIEW) {
                throw new RuntimeException('Rekening ini sudah diperiksa sebelumnya.');
            }

            $this->ensureNotOwn($account->user_id, $reviewer);

            $account->update($changes + ['reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            if ($account->status === BankAccount::STATUS_APPROVED) {
                $this->replacePrevious($account);
            }

            $this->audit('bank_account.'.$account->status, $account, ['note' => $account->review_note], $reviewer);

            return $account;
        });
    }

    /** Rekening lama tetap dipakai sampai rekening baru aktif. */
    private function replacePrevious(BankAccount $account): void
    {
        BankAccount::where('user_id', $account->user_id)
            ->whereKeyNot($account->id)
            ->where('status', BankAccount::STATUS_APPROVED)
            ->whereNull('replaced_at')
            ->update(['replaced_at' => $account->active_at]);
    }

    private function audit(string $action, BankAccount $account, array $extra = [], ?User $actor = null): void
    {
        AuditLog::record($action, BankAccount::class, $account->id, null, [
            'user_id' => $account->user_id,
            'account' => $account->masked(),
            'account_name' => $account->account_name,
        ] + $extra, $actor);
    }

    /** Email gagal tidak boleh membatalkan aksi yang sudah tersimpan. */
    private function notify(BankAccount $account, string $event): void
    {
        try {
            $account->user->notify(new BankAccountNotification($account, $event));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Superadmin tidak boleh memeriksa rekeningnya sendiri. */
    private function ensureNotOwn(string $ownerId, User $reviewer): void
    {
        if ($ownerId === $reviewer->id) {
            throw new RuntimeException('Rekening milik Anda sendiri harus diperiksa superadmin lain.');
        }
    }

    private function checkKey(User $user): string
    {
        return 'bank-account-check:'.$user->id;
    }
}
