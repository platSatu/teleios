<?php

namespace App\Jobs;

use App\Models\PgInvoice;
use App\Models\PgWebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Kirim event pembayaran ke webhook_url website pemakai, ditandatangani
 * X-Pay-Signature = HMAC-SHA256(secret, timestamp + "." + body). Antrean
 * sendiri ('payments') supaya website yang lambat tidak menahan fitur lain;
 * dicoba ulang 1m, 5m, 30m, 2j, 6j. Setiap percobaan dicatat.
 */
class SendPgWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public int $timeout = 20;

    public function __construct(public string $invoiceId, public string $event)
    {
        $this->onQueue('payments');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600];
    }

    public function handle(): void
    {
        $invoice = PgInvoice::with('merchant')->find($this->invoiceId);
        $merchant = $invoice?->merchant;

        if (! $merchant || ! $merchant->webhook_url) {
            return;
        }

        $body = json_encode(['event' => $this->event, 'data' => $invoice->toApi()], JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $status = null;
        $error = null;

        try {
            self::assertPublicUrl($merchant->webhook_url);

            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'X-Pay-Event' => $this->event,
                'X-Pay-Timestamp' => $timestamp,
                'X-Pay-Signature' => $merchant->sign($timestamp, $body),
            ])->withBody($body, 'application/json')->withoutRedirecting()->post($merchant->webhook_url);

            $status = $response->status();

            if (! $response->successful()) {
                $error = 'HTTP '.$status;
            }
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
        }

        PgWebhookLog::create([
            'pg_invoice_id' => $invoice->id,
            'event' => $this->event,
            'attempt' => $this->attempts(),
            'http_status' => $status,
            'error' => $error,
        ]);

        if ($error) {
            throw new RuntimeException("Webhook {$this->event} gagal: {$error}");
        }
    }

    /** Tolak URL ke jaringan internal (localhost, IP privat) -- cegah SSRF. */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            throw new RuntimeException('Webhook URL wajib https.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new RuntimeException('Host webhook tidak ditemukan.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Webhook URL tidak boleh mengarah ke jaringan internal.');
            }
        }
    }
}
