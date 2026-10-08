<?php

namespace App\Services\PaymentGateway;

use App\Models\DuitkuSetting;
use App\Models\PgInvoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Duitku API v2 (inquiry langsung, BUKAN popup POP) khusus Payment Gateway,
 * supaya popup/halaman checkout teleios bisa menampilkan VA/QRIS/e-wallet
 * sendiri dengan nama website pemakai. Kredensial = merchant Duitku yang
 * sama (Superadmin > Pengaturan Duitku); sandbox/production mengikuti
 * setting itu (konexa = sandbox, bizbos = production).
 */
class PgDuitkuClient
{
    private string $merchantCode;

    private string $apiKey;

    private bool $sandbox;

    public function __construct()
    {
        $setting = DuitkuSetting::current();

        if (! $setting->isConfigured()) {
            throw new RuntimeException('Duitku belum dikonfigurasi di Superadmin > Pengaturan Duitku.');
        }

        $this->merchantCode = (string) $setting->activeMerchantCode();
        $this->apiKey = (string) $setting->activeApiKey();
        $this->sandbox = $setting->isSandbox();
    }

    private function url(string $path): string
    {
        return ($this->sandbox ? 'https://sandbox.duitku.com' : 'https://passport.duitku.com').'/webapi/api/merchant/'.$path;
    }

    /**
     * Buat transaksi untuk metode yang dipilih pembeli.
     *
     * @return array{reference: ?string, va_number: ?string, qr_string: ?string, payment_url: ?string}
     */
    public function inquiry(PgInvoice $invoice, string $method): array
    {
        $amount = (int) $invoice->amount;
        $minutes = max(1, (int) ceil(now()->diffInSeconds($invoice->expires_at, false) / 60));

        $response = Http::acceptJson()->timeout(20)->post($this->url('v2/inquiry'), [
            'merchantCode' => $this->merchantCode,
            'paymentAmount' => $amount,
            'paymentMethod' => $method,
            'merchantOrderId' => $invoice->order_number,
            'productDetails' => mb_substr($invoice->description, 0, 255),
            'customerVaName' => mb_substr($invoice->merchant->name, 0, 20),
            'email' => $invoice->customer_email ?: 'noreply@example.com',
            'phoneNumber' => $invoice->customer_phone ?: '',
            'itemDetails' => [['name' => mb_substr($invoice->description, 0, 50), 'price' => $amount, 'quantity' => 1]],
            'callbackUrl' => route('pg.duitku.callback'),
            'returnUrl' => route('pg.checkout.show', $invoice->token),
            'expiryPeriod' => $minutes,
            'signature' => md5($this->merchantCode.$invoice->order_number.$amount.$this->apiKey),
        ]);

        $data = $response->json() ?? [];

        if ($response->failed() || ($data['statusCode'] ?? null) !== '00') {
            throw new RuntimeException($data['statusMessage'] ?? $data['Message'] ?? 'Duitku menolak transaksi (HTTP '.$response->status().').');
        }

        return [
            'reference' => $data['reference'] ?? null,
            'va_number' => $data['vaNumber'] ?? null,
            'qr_string' => $data['qrString'] ?? null,
            'payment_url' => $data['paymentUrl'] ?? null,
        ];
    }

    /**
     * Callback v2 ditandatangani md5(merchantCode+amount+merchantOrderId+apiKey);
     * format HMAC-SHA256 (dipakai callback POP akun ini) juga diterima.
     */
    public function verifyCallback(array $n): bool
    {
        if (! isset($n['signature'], $n['merchantCode'], $n['amount'], $n['merchantOrderId'])) {
            return false;
        }

        if (! hash_equals($this->merchantCode, (string) $n['merchantCode'])) {
            return false;
        }

        $message = $n['merchantCode'].$n['amount'].$n['merchantOrderId'];
        $signature = (string) $n['signature'];

        return hash_equals(md5($message.$this->apiKey), $signature)
            || hash_equals(hash_hmac('sha256', $message, $this->apiKey), $signature);
    }

    /**
     * Konfirmasi langsung ke Duitku (resultCode callback tidak ikut
     * ditandatangani). null = lunas & nominal cocok; selain itu alasannya.
     */
    public function unconfirmedReason(string $orderNumber, int $amount): ?string
    {
        try {
            $response = Http::acceptJson()->timeout(15)->post($this->url('transactionStatus'), [
                'merchantCode' => $this->merchantCode,
                'merchantOrderId' => $orderNumber,
                'signature' => md5($this->merchantCode.$orderNumber.$this->apiKey),
            ]);
        } catch (Throwable) {
            return 'unreachable';
        }

        $data = $response->json() ?? [];

        if (($data['statusCode'] ?? null) !== '00') {
            return 'status '.($data['statusCode'] ?? 'HTTP '.$response->status());
        }

        if ((int) round((float) ($data['amount'] ?? 0)) !== $amount) {
            return 'amount mismatch';
        }

        return null;
    }
}
