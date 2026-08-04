<?php

namespace App\Services\Payment;

use App\Models\Deposit;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Duitku's POP integration directly via Laravel's HTTP client —
 * NOT through the installed duitkupg/duitku-php composer package. That
 * package's Pop::createInvoice() signs requests with a plain
 * hash('sha256', merchantCode.timestamp.apiKey), which Duitku's current
 * /api/merchant/createinvoice endpoint rejects (this is what caused the
 * "Merchant Not Found" error even with valid production credentials —
 * an auth failure, generically messaged). The current signature is
 * HMAC-SHA256 keyed by the API key instead: hash_hmac('sha256',
 * merchantCode.timestamp, apiKey). See createInvoice() below.
 *
 * Renders Duitku's own in-page "popup" checkout widget (duitku.js +
 * checkout.process()) rather than a full-page redirect to Duitku's
 * hosted payment page — see resources/views/user/deposit/pay.blade.php,
 * which is what DepositController::proceedToDuitku() renders once
 * createInvoice() succeeds. paymentUrl is still returned/stored as a
 * plain-link fallback for when JS/the widget script fails to load.
 */
class DuitkuService
{
    public function __construct(
        private readonly string $merchantCode,
        private readonly string $apiKey,
        private readonly bool $sandbox,
    ) {
    }

    public static function make(): self
    {
        $merchantCode = config('services.duitku.merchant_code');
        $apiKey = config('services.duitku.api_key');

        if (! $merchantCode || ! $apiKey) {
            throw new RuntimeException(
                'Duitku belum dikonfigurasi — set DUITKU_MERCHANT_CODE dan DUITKU_API_KEY di .env.'
            );
        }

        return new self($merchantCode, $apiKey, (bool) config('services.duitku.sandbox', true));
    }

    /**
     * https://api-sandbox.duitku.com or https://api-prod.duitku.com —
     * the REST API host (invoice creation, transaction status).
     * Separate from widgetScriptUrl() below, which is a different
     * subdomain (app-sandbox/app-prod) serving the JS checkout widget.
     */
    private function apiBaseUrl(): string
    {
        return $this->sandbox ? 'https://api-sandbox.duitku.com' : 'https://api-prod.duitku.com';
    }

    /**
     * The duitku.js checkout widget script — sandbox and production are
     * different subdomains, so this MUST match whichever environment
     * createInvoice() actually talked to, or Duitku will reject the
     * `reference` as belonging to the wrong environment.
     */
    public function widgetScriptUrl(): string
    {
        return $this->sandbox
            ? 'https://app-sandbox.duitku.com/lib/js/duitku.js'
            : 'https://app-prod.duitku.com/lib/js/duitku.js';
    }

    /**
     * Builds the Duitku POP invoice payload for a PENDING Deposit and
     * calls createinvoice. Returns the decoded response as an
     * associative array (raw — callers store both the payload sent and
     * this response on PaymentTransaction, then interpret statusCode
     * themselves).
     *
     * merchantOrderId = Deposit::reference_number (already unique, see
     * Deposit::boot()) — this is the same value Duitku echoes back in
     * both transactionStatus() and the callback, so it's what
     * DuitkuCallbackController looks the Deposit back up by.
     *
     * @return array{raw: array, paymentUrl: ?string, reference: ?string, statusCode: ?string, statusMessage: ?string, request_payload: array}
     */
    public function createInvoice(Deposit $deposit): array
    {
        $deposit->loadMissing('user');
        $user = $deposit->user;

        $amount = (int) round((float) $deposit->amount);
        $name = $user?->name ?: 'Customer';
        [$firstName, $lastName] = $this->splitName($name);
        $phone = $user?->handphone ?: '';
        $email = $user?->email ?: 'noreply@example.com';

        $payload = [
            'paymentAmount' => $amount,
            'merchantOrderId' => $deposit->reference_number,
            'productDetails' => 'Top Up Saldo Wallet',
            'additionalParam' => '',
            'merchantUserInfo' => (string) $deposit->user_id,
            'customerVaName' => $name,
            'email' => $email,
            'phoneNumber' => $phone,
            'itemDetails' => [
                [
                    'name' => 'Top Up Saldo Wallet',
                    'price' => $amount,
                    'quantity' => 1,
                ],
            ],
            'customerDetail' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phoneNumber' => $phone,
                'billingAddress' => $this->address($firstName, $lastName, $phone),
                'shippingAddress' => $this->address($firstName, $lastName, $phone),
            ],
            'callbackUrl' => route('deposit.duitku.callback'),
            'returnUrl' => route('deposit.duitku.return', $deposit),
            'expiryPeriod' => (int) config('services.duitku.expiry_minutes', 60),
        ];

        $timestamp = (string) round(microtime(true) * 1000);
        $signature = hash_hmac('sha256', $this->merchantCode . $timestamp, $this->apiKey);

        $response = Http::withHeaders([
            'x-duitku-signature' => $signature,
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-merchantcode' => $this->merchantCode,
        ])->post($this->apiBaseUrl() . '/api/merchant/createinvoice', $payload);

        $decoded = $response->json() ?? [];

        if ($response->failed() && empty($decoded)) {
            throw new RuntimeException('Duitku Error: ' . $response->status() . ' response: ' . $response->body());
        }

        return [
            'raw' => $decoded,
            'paymentUrl' => $decoded['paymentUrl'] ?? null,
            'reference' => $decoded['reference'] ?? null,
            'statusCode' => $decoded['statusCode'] ?? null,
            'statusMessage' => $decoded['statusMessage'] ?? ($response->failed() ? $response->body() : null),
            'request_payload' => $payload,
        ];
    }

    /**
     * Same signature algorithm Duitku's callback notification has used
     * for years (unlike createInvoice's, this one has NOT changed to
     * HMAC — confirmed against the current Duitku documentation):
     * md5(merchantCode + amount + merchantOrderId + apiKey). Verified
     * against a plain array (e.g. $request->all()) rather than reading
     * $_POST directly, so it's usable/testable from a normal Laravel
     * request lifecycle.
     */
    public function verifyCallbackSignature(array $notification): bool
    {
        if (! isset($notification['signature'], $notification['merchantCode'], $notification['amount'], $notification['merchantOrderId'])) {
            return false;
        }

        $expected = md5(
            $notification['merchantCode']
            . $notification['amount']
            . $notification['merchantOrderId']
            . $this->apiKey
        );

        return hash_equals($expected, (string) $notification['signature']);
    }

    /**
     * Duitku wants firstName/lastName separately; this app only
     * collects one `name` field, so split naively on the first space.
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0] ?: 'Customer', $parts[1] ?? ''];
    }

    /**
     * billingAddress/shippingAddress are required object shapes in the
     * payload, but this app doesn't collect a real address anywhere —
     * filled with placeholders rather than omitted, since an entirely
     * missing address object is more likely to be rejected than one
     * with generic values.
     */
    private function address(string $firstName, string $lastName, string $phone): array
    {
        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'address' => '-',
            'city' => '-',
            'postalCode' => '00000',
            'phone' => $phone,
            'countryCode' => 'ID',
        ];
    }
}
