<?php

namespace App\Services\Payment;

use App\Models\DuitkuDisbursementSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Duitku's DISBURSEMENT API — a completely separate Duitku
 * product from App\Services\Payment\DuitkuService (which only ever
 * RECEIVES money, via the payment-gateway/createinvoice flow). This one
 * SENDS money out to a bank account ("Tarik Saldo" feature, didiskusikan
 * 22 September 2026) — the "Transfer Online" flavor specifically (there
 * are also Clearing/LLG-RTGS-H2H-BIFAST and Cash Out flavors in Duitku's
 * docs, both explicitly out of scope here — Transfer Online covers every
 * bank this app needs and is the simplest of the three).
 *
 * Different credentials (userId + email + secretKey via
 * App\Models\DuitkuDisbursementSetting, NOT merchantCode + apiKey) and a
 * different, older signing scheme — plain SHA256 string concatenation,
 * NOT HMAC-SHA256 like DuitkuService. Formulas below are copied
 * verbatim from https://docs.duitku.com/disbursement/en/ (Transfer
 * Online section) — do NOT "clean up" the concatenation order, Duitku
 * verifies byte-for-byte.
 *
 * Every write call here moves real money — always call inquiry() first
 * and only call transfer() with the disburseId inquiry() returned; never
 * skip straight to transfer() with a guessed disburseId.
 */
class DuitkuDisbursementService
{
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $secretKey,
        private readonly bool $sandbox,
    ) {
    }

    public static function make(): self
    {
        $setting = DuitkuDisbursementSetting::current();

        if (! $setting->isConfigured()) {
            throw new RuntimeException(
                'Duitku Disbursement belum dikonfigurasi — isi User ID, Email, dan Secret Key ('
                . ($setting->isSandbox() ? 'Sandbox' : 'Production')
                . ') di Superadmin > Deposits > Pengaturan Duitku Disbursement.'
            );
        }

        return new self(
            $setting->activeUserId(),
            $setting->activeEmail(),
            $setting->activeSecretKey(),
            $setting->isSandbox(),
        );
    }

    /**
     * Sandbox dan production Disbursement hidup di HOST yang beda
     * (bukan cuma path suffix seperti createinvoice) — sandbox.duitku.com
     * vs passport.duitku.com, keduanya di bawah /webapi/api/disbursement.
     * Endpoint sandbox juga punya suffix "sandbox" sendiri di path-nya
     * (inquirysandbox, transfersandbox, dst) — DITANGANI per method di
     * bawah, bukan di sini, karena checkbalance/inquirystatus/listBank
     * TIDAK punya suffix sandbox sama sekali (host-nya saja yang beda).
     */
    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.duitku.com/webapi/api/disbursement'
            : 'https://passport.duitku.com/webapi/api/disbursement';
    }

    private function timestampMs(): float
    {
        return round(microtime(true) * 1000);
    }

    /**
     * 9 digit numerik, sesuai contoh Duitku ("000000001278") — bukan
     * dari user, digenerate di sini setiap kali transfer() dipanggil.
     * Epoch detik (10 digit) di-mod supaya pas 9 digit, digabung 3 digit
     * random di ujung supaya dua transfer dalam detik yang sama tetap
     * beda custRefNumber.
     */
    public function generateCustRefNumber(): string
    {
        $epoch = (int) (time() % 1000000);
        $rand = random_int(0, 999);

        return str_pad((string) $epoch, 6, '0', STR_PAD_LEFT) . str_pad((string) $rand, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Langkah 1/2 — cek rekening tujuan valid + dapatkan disburseId yang
     * WAJIB dipakai di transfer(). TIDAK memindahkan uang sama sekali,
     * cuma validasi.
     *
     * @return array{disburseId: ?string, accountName: ?string, responseCode: ?string, responseDesc: ?string, raw: array}
     */
    public function inquiry(string $bankCode, string $bankAccount, int $amountTransfer, string $purpose, ?string $senderName = null): array
    {
        $timestamp = $this->timestampMs();

        $payload = [
            'userId' => (int) $this->userId,
            'email' => $this->email,
            'bankCode' => $bankCode,
            'bankAccount' => $bankAccount,
            'amountTransfer' => $amountTransfer,
            'purpose' => $purpose,
            'timestamp' => $timestamp,
            'signature' => hash('sha256', $this->email . $timestamp . $bankCode . $bankAccount . $amountTransfer . $purpose . $this->secretKey),
        ];

        if ($senderName) {
            $payload['senderName'] = $senderName;
        }

        $path = $this->sandbox ? '/inquirysandbox' : '/inquiry';
        $decoded = $this->post($path, $payload);

        return [
            'disburseId' => isset($decoded['disburseId']) ? (string) $decoded['disburseId'] : null,
            'accountName' => $decoded['accountName'] ?? null,
            'responseCode' => $decoded['responseCode'] ?? null,
            'responseDesc' => $decoded['responseDesc'] ?? null,
            'raw' => $decoded,
        ];
    }

    /**
     * Langkah 2/2 — EKSEKUSI transfer beneran, pakai disburseId dari
     * inquiry() di atas. Ini yang benar-benar memindahkan uang.
     *
     * @return array{responseCode: ?string, responseDesc: ?string, custRefNumber: string, raw: array}
     */
    public function transfer(
        string $disburseId,
        string $bankCode,
        string $bankAccount,
        int $amountTransfer,
        string $accountName,
        string $purpose,
        ?string $custRefNumber = null,
    ): array {
        $timestamp = $this->timestampMs();
        $custRefNumber ??= $this->generateCustRefNumber();

        $payload = [
            'disburseId' => $disburseId,
            'userId' => (int) $this->userId,
            'email' => $this->email,
            'bankCode' => $bankCode,
            'bankAccount' => $bankAccount,
            'amountTransfer' => $amountTransfer,
            'accountName' => $accountName,
            'custRefNumber' => $custRefNumber,
            'purpose' => $purpose,
            'timestamp' => $timestamp,
            'signature' => hash(
                'sha256',
                $this->email . $timestamp . $bankCode . $bankAccount . $accountName
                . $custRefNumber . $amountTransfer . $purpose . $disburseId . $this->secretKey
            ),
        ];

        $path = $this->sandbox ? '/transfersandbox' : '/transfer';
        $decoded = $this->post($path, $payload);

        return [
            'responseCode' => $decoded['responseCode'] ?? null,
            'responseDesc' => $decoded['responseDesc'] ?? null,
            'custRefNumber' => $custRefNumber,
            'raw' => $decoded,
        ];
    }

    /**
     * Saldo Disbursement Duitku platform (bukan saldo Wallet internal
     * manapun) — dipakai superadmin/halaman monitoring buat cek dana
     * riil sebelum menyetujui batch penarikan besar.
     *
     * @return array{balance: ?float, effectiveBalance: ?float, raw: array}
     */
    public function checkBalance(): array
    {
        $timestamp = $this->timestampMs();

        $payload = [
            'userId' => (int) $this->userId,
            'email' => $this->email,
            'timestamp' => $timestamp,
            'signature' => hash('sha256', $this->email . $timestamp . $this->secretKey),
        ];

        // Tidak ada suffix "sandbox" untuk checkbalance/inquirystatus/
        // listBank menurut dokumen Duitku — host yang beda sudah cukup
        // membedakan sandbox vs production untuk 3 endpoint ini.
        $decoded = $this->post('/checkbalance', $payload);

        return [
            'balance' => isset($decoded['balance']) ? (float) $decoded['balance'] : null,
            'effectiveBalance' => isset($decoded['effectiveBalance']) ? (float) $decoded['effectiveBalance'] : null,
            'raw' => $decoded,
        ];
    }

    /**
     * Status transfer yang sudah pernah diajukan — dipakai buat polling
     * transfer yang responseCode-nya "80 waiting for callback"/timeout,
     * bukan buat transfer yang baru mau dibuat.
     */
    public function inquiryStatus(string $disburseId): array
    {
        $timestamp = $this->timestampMs();

        $payload = [
            'disburseId' => $disburseId,
            'userId' => (int) $this->userId,
            'email' => $this->email,
            'timestamp' => $timestamp,
            'signature' => hash('sha256', $this->email . $timestamp . $disburseId . $this->secretKey),
        ];

        return $this->post('/inquirystatus', $payload);
    }

    /**
     * Daftar bank yang didukung Duitku Disbursement, dengan bankCode
     * masing-masing — dipakai buat dropdown "Bank Tujuan" di form Tarik
     * Saldo, bukan daftar hardcode di frontend (Duitku bisa menambah/
     * menghapus bank yang didukung sewaktu-waktu).
     *
     * @return array<int, array{bankCode: string, bankName: string, maxAmountTransfer: ?string}>
     */
    public function listBanks(): array
    {
        $timestamp = $this->timestampMs();

        $payload = [
            'userId' => (int) $this->userId,
            'email' => $this->email,
            'timestamp' => $timestamp,
            'signature' => hash('sha256', $this->email . $timestamp . $this->secretKey),
        ];

        $decoded = $this->post('/listBank', $payload);

        return $decoded['Banks'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = Http::asJson()->acceptJson()->post($this->baseUrl() . $path, $payload);

        $decoded = $response->json() ?? [];

        if ($response->failed() && empty($decoded)) {
            throw new RuntimeException('Duitku Disbursement Error: ' . $response->status() . ' response: ' . $response->body());
        }

        return $decoded;
    }
}
