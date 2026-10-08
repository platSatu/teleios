<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Jobs\SendPgWebhook;
use App\Models\PgFee;
use App\Models\PgInvoice;
use App\Models\PgMerchant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Menu "Payment Gateway" milik company (satu website per company).
 * Tab: Ringkasan, Transaksi, Saldo, Pengaturan, Dokumentasi. Pengaturan &
 * secret hanya bisa diubah owner. Akun baru berstatus "pending" sampai
 * diaktifkan superadmin.
 */
class PgMerchantController extends Controller
{
    use ResolvesCompanyContext;

    private const TABS = ['ringkasan', 'transaksi', 'saldo', 'pengaturan', 'dokumentasi'];

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $merchant = PgMerchant::where('company_id', $context->company->id)->first();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'ringkasan';

        $data = compact('merchant', 'tab') + ['isOwner' => $context->isOwner, 'fees' => PgFee::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()];

        if ($merchant) {
            $paidThisMonth = $merchant->invoices()->where('status', PgInvoice::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth());

            $data['summary'] = [
                'count' => (clone $paidThisMonth)->count(),
                'gross' => (int) (clone $paidThisMonth)->sum('amount'),
                'net' => (int) (clone $paidThisMonth)->sum('net_amount'),
                'pending' => $merchant->invoices()->where('status', PgInvoice::STATUS_PENDING)->where('expires_at', '>', now())->count(),
            ];

            if ($tab === 'transaksi') {
                $data['invoices'] = $merchant->invoices()
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                    ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('external_id', 'like', '%'.$request->string('search').'%')->orWhere('customer_name', 'like', '%'.$request->string('search').'%')))
                    ->latest()
                    ->paginate(25)
                    ->withQueryString();
            }

            if ($tab === 'saldo') {
                $data['ledger'] = $merchant->ledger()->latest('created_at')->paginate(25)->withQueryString();
            }
        }

        return view('payment-gateway.index', $data);
    }

    /** Buat akun (pertama kali) atau simpan pengaturan. Owner saja. */
    public function save(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        abort_unless($context->isOwner, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'logo_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'webhook_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'allowed_domains' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'Nama website', 'webhook_url' => 'Webhook URL', 'logo_url' => 'URL logo']);

        if (! empty($validated['webhook_url'])) {
            try {
                SendPgWebhook::assertPublicUrl($validated['webhook_url']);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['webhook_url' => $e->getMessage()]);
            }
        }

        $domains = collect(preg_split('/[\s,]+/', strtolower($validated['allowed_domains'] ?? '')))
            ->map(fn ($d) => preg_replace('#^https?://#', '', rtrim(trim($d), '/')))
            ->filter(fn ($d) => preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/', $d))
            ->unique()->values()->all();

        $attributes = [
            'name' => $validated['name'],
            'logo_url' => $validated['logo_url'] ?? null,
            'webhook_url' => $validated['webhook_url'] ?? null,
            'allowed_domains' => $domains,
        ];

        $merchant = PgMerchant::where('company_id', $context->company->id)->first();

        if ($merchant) {
            $merchant->update($attributes);

            return redirect()->route('payment-gateway.index', ['tab' => 'pengaturan'])->with('success', 'Pengaturan disimpan.');
        }

        $secret = PgMerchant::newSecret();

        PgMerchant::create($attributes + [
            'company_id' => $context->company->id,
            'api_key' => PgMerchant::newApiKey(),
            'secret' => $secret,
            'status' => PgMerchant::STATUS_PENDING,
        ]);

        return redirect()->route('payment-gateway.index', ['tab' => 'pengaturan'])
            ->with('success', 'Akun Payment Gateway dibuat dan menunggu aktivasi dari tim teleios.')
            ->with('pg_secret', $secret);
    }

    /** Secret lama langsung tidak berlaku. Owner saja. */
    public function regenerateSecret(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        abort_unless($context->isOwner, 403);

        $merchant = PgMerchant::where('company_id', $context->company->id)->firstOrFail();
        $secret = PgMerchant::newSecret();
        $merchant->update(['secret' => $secret]);

        return redirect()->route('payment-gateway.index', ['tab' => 'pengaturan'])
            ->with('success', 'Secret baru dibuat. Secret lama sudah tidak berlaku -- segera ganti di server website Anda.')
            ->with('pg_secret', $secret);
    }

    /** Kirim ulang webhook status terakhir satu invoice. */
    public function resendWebhook(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $merchant = PgMerchant::where('company_id', $context->company->id)->firstOrFail();
        $invoice = $merchant->invoices()->whereKey($id)->firstOrFail();

        SendPgWebhook::dispatch($invoice->id, 'invoice.'.$invoice->status);

        return back()->with('success', "Webhook {$invoice->external_id} dijadwalkan ulang.");
    }
}
