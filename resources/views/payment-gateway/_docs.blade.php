<div style="max-width:860px">
    <h5>1. Alur singkat</h5>
    <ol class="text-muted">
        <li>Server website Anda membuat invoice lewat API (secret hanya di server).</li>
        <li>Website menampilkan popup teleios dengan <code>token</code> invoice.</li>
        <li>Pembeli memilih metode &amp; membayar. teleios memverifikasi ke Duitku.</li>
        <li>teleios mengirim webhook <code>invoice.paid</code> ke server Anda. <strong>Tandai lunas hanya dari webhook / cek status API</strong>, jangan dari browser.</li>
    </ol>

    <h5 class="mt-4">2. Header setiap request</h5>
<pre class="bg-light p-3 rounded small"><code>X-Pay-Key:       {{ $merchant->api_key }}
X-Pay-Timestamp: (unix detik, mis. 1791400000)
X-Pay-Signature: HMAC-SHA256(secret, timestamp + "." + body_json)
Content-Type:    application/json</code></pre>

    <h5 class="mt-4">3. Endpoint</h5>
    <table class="table table-sm small">
        <tr><td><code>POST {{ url('/api/pay/v1/invoices') }}</code></td><td>Buat invoice. Kirim ulang dengan <code>external_id</code> sama = invoice yang sama (aman di-retry).</td></tr>
        <tr><td><code>GET {{ url('/api/pay/v1/invoices/{id atau external_id}') }}</code></td><td>Cek status.</td></tr>
        <tr><td><code>POST {{ url('/api/pay/v1/invoices/{id}/cancel') }}</code></td><td>Batalkan (selama metode belum dipilih).</td></tr>
        <tr><td><code>GET {{ url('/api/pay/v1/balance') }}</code></td><td>Saldo.</td></tr>
    </table>

    <h5 class="mt-4">4. Contoh (PHP / Laravel)</h5>
@verbatim
<pre class="bg-light p-3 rounded small"><code>$body = json_encode([
    'external_id' => 'ORDER-1001',          // ID pesanan di website Anda (unik)
    'amount' => 250000,                     // rupiah, tanpa desimal
    'description' => 'Tiket Konser A',
    'customer' => ['name' => 'Budi', 'email' => 'budi@mail.com', 'phone' => '0812...'],
    'return_url' => 'https://website-anda.com/pesanan/1001',
    'expiry_minutes' => 30,
]);
$ts = (string) time();
$sig = hash_hmac('sha256', $ts.'.'.$body, env('TELEIOS_PAY_SECRET'));

$res = Http::withHeaders([
    'X-Pay-Key' => env('TELEIOS_PAY_KEY'),
    'X-Pay-Timestamp' => $ts,
    'X-Pay-Signature' => $sig,
])->withBody($body, 'application/json')->post('https://TELEIOS/api/pay/v1/invoices');

$token = $res->json('data.token');   // dipakai untuk popup</code></pre>
@endverbatim

    <h5 class="mt-4">5. Tampilkan popup di halaman Anda</h5>
<pre class="bg-light p-3 rounded small"><code>&lt;script src="{{ asset('pay/v1/checkout.js') }}"&gt;&lt;/script&gt;
&lt;script&gt;
TeleiosPay.open('TOKEN_DARI_SERVER', {
  onSuccess: function () { location.href = '/pesanan/1001'; }, // tampilan saja
  onClose:   function () { }
});
&lt;/script&gt;</code></pre>
    <p class="text-muted small">Tanpa script: arahkan pembeli ke <code>data.checkout_url</code>.</p>

    <h5 class="mt-4">6. Verifikasi webhook</h5>
@verbatim
<pre class="bg-light p-3 rounded small"><code>$raw = $request->getContent();
$expected = hash_hmac('sha256', $request->header('X-Pay-Timestamp').'.'.$raw, env('TELEIOS_PAY_SECRET'));
if (! hash_equals($expected, (string) $request->header('X-Pay-Signature'))) abort(401);

$event = $request->input('event');        // invoice.paid | invoice.expired | invoice.failed | invoice.cancelled
$data  = $request->input('data');         // id, external_id, status, amount, fee, net_amount, ...
// Proses idempotent berdasarkan external_id, lalu balas HTTP 200.</code></pre>
@endverbatim
    <p class="text-muted small mb-0">Balasan selain 2xx akan dicoba ulang otomatis: 1 menit, 5 menit, 30 menit, 2 jam, 6 jam.</p>
</div>
