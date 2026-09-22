<?php

namespace App\Http\Controllers\Tagihan\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\TagihanPenerima;
use App\Services\Payment\DuitkuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman publik bayar Tagihan -- TANPA login (lihat routes/web.php's
 * 'tagihan/{branchSlug}/{token}'). Pelanggan langsung lihat nominal +
 * denda (kalau ada) lalu bayar lewat popup Duitku, mirip alur
 * App\Http\Controllers\User\Deposit\DepositController tapi tanpa
 * Auth::user() sama sekali -- {token} (App\Models\TagihanPenerima::
 * public_token) yang jadi satu-satunya kunci akses baris ini.
 *
 * {branchSlug} di URL murni kosmetik (biar link enak dibaca,
 * app.konexa.id/tagihan/bogor/{token}) -- DIVALIDASI cocok dengan
 * BranchOffice milik baris ini (lihat resolvePenerima()), tapi bukan
 * bagian dari kunci akses; token tetap satu-satunya yang menentukan
 * baris mana yang dibuka.
 *
 * Tidak ada create()/store() di sini -- baris TagihanPenerima yang mau
 * dibuka SUDAH ada, dibuat lewat App\Http\Controllers\Tagihan\
 * TagihanController (admin). Controller ini murni "lihat lalu bayar".
 */
class TagihanPublicController extends Controller
{
    public function show(Request $request, string $branchSlug, string $token): View|RedirectResponse
    {
        $penerima = $this->resolvePenerima($branchSlug, $token);

        // Denda dihitung ULANG tiap kali halaman ini dibuka (bukan
        // dibaca dari kolom denda_amount yang lama) supaya nominal yang
        // dilihat pelanggan selalu real-time sampai detik invoice
        // Duitku benar-benar dibuat (proceedToDuitku() di bawah yang
        // baru mengunci nilainya ke kolom).
        $dendaSaatIni = $penerima->hitungDenda();

        $checkoutTimeoutMinutes = (int) config('services.duitku.checkout_timeout_minutes', 10);

        return view('tagihan.public.show', compact('penerima', 'dendaSaatIni', 'checkoutTimeoutMinutes'));
    }

    /**
     * Pelanggan menekan "Bayar Sekarang" di halaman show() -- baru di
     * sinilah denda dikunci ke kolom (isi kolom denda_amount berhenti
     * berubah walau tier-nya diedit admin setelahnya, lihat docblock
     * App\Models\TagihanPenerima::hitungDenda()) dan Duitku benar-benar
     * dipanggil. Sama pola timer/expired dengan App\Http\Controllers\
     * User\Deposit\DepositController::proceedToDuitku().
     */
    public function proceedToDuitku(Request $request, string $branchSlug, string $token): View|RedirectResponse
    {
        $penerima = $this->resolvePenerima($branchSlug, $token);

        if ($penerima->status !== TagihanPenerima::STATUS_BELUM_BAYAR || $this->hasExpiredWindow($penerima)) {
            return $this->rejectedView($penerima, 'Tagihan ini sudah tidak menunggu pembayaran.');
        }

        $duitku = DuitkuService::make();

        DB::transaction(function () use ($penerima) {
            $penerima->update(['denda_amount' => $penerima->hitungDenda()]);
        });
        $penerima->refresh();

        try {
            $result = $duitku->createInvoiceForTagihan($penerima, $branchSlug);
        } catch (\Throwable $e) {
            report($e);

            return $this->rejectedView($penerima, 'Gagal menghubungi Duitku. Silakan coba lagi. ('.$e->getMessage().')', retry: true);
        }

        if ($result['statusCode'] !== '00' || ! $result['reference']) {
            PaymentTransaction::create([
                'reference_type' => TagihanPenerima::class,
                'reference_id' => $penerima->id,
                'provider' => 'DUITKU',
                'provider_transaction_id' => $result['reference'],
                'amount' => (float) $penerima->amount + (float) $penerima->denda_amount,
                'currency' => 'IDR',
                'status' => 'FAILED',
                'request_payload' => $result['request_payload'],
                'response_payload' => $result['raw'],
                'failure_reason' => $result['statusMessage'] ?? 'Duitku tidak mengembalikan reference pembayaran.',
            ]);

            return $this->rejectedView($penerima, $result['statusMessage'] ?? 'Duitku menolak permintaan pembayaran ini. Silakan coba lagi.', retry: true);
        }

        DB::transaction(function () use ($penerima, $result) {
            PaymentTransaction::create([
                'reference_type' => TagihanPenerima::class,
                'reference_id' => $penerima->id,
                'provider' => 'DUITKU',
                'provider_transaction_id' => $result['reference'],
                'amount' => (float) $penerima->amount + (float) $penerima->denda_amount,
                'currency' => 'IDR',
                'status' => 'PENDING',
                'request_payload' => $result['request_payload'],
                'response_payload' => $result['raw'],
            ]);

            // Sama window yang dikirim ke Duitku sendiri (expiryPeriod)
            // -- dipakai App\Console\Commands\ProcessTagihanExpiry buat
            // menandai 'kadaluarsa' begitu waktunya lewat tanpa
            // konfirmasi. lockForUpdate() tidak diperlukan di sini
            // (baris ini baru sekali disentuh dalam transaksi ini,
            // beda dari webhook yang bisa race dengan job terjadwal).
            $penerima->update([
                'expires_at' => now()->addMinutes((int) config('services.duitku.expiry_minutes', 60)),
            ]);

            AuditLog::create([
                'actor_type' => 'SYSTEM',
                'actor_id' => null,
                'action' => 'TAGIHAN_INVOICE_CREATED',
                'entity_type' => 'TagihanPenerima',
                'entity_id' => $penerima->id,
                'new_value' => ['amount' => $penerima->amount, 'denda_amount' => $penerima->denda_amount],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        return view('tagihan.public.pay', [
            'penerima' => $penerima,
            'branchSlug' => $branchSlug,
            'reference' => $result['reference'],
            'paymentUrl' => $result['paymentUrl'],
            'widgetScriptUrl' => $duitku->widgetScriptUrl(),
        ]);
    }

    /**
     * Tempat browser DIKEMBALIKAN setelah percobaan bayar -- informasi
     * saja, TIDAK pernah menandai lunas (itu murni hak
     * TagihanDuitkuCallbackController lewat webhook server-to-server),
     * sama prinsipnya dengan DepositController::returnFromDuitku().
     */
    public function returnFromDuitku(Request $request, string $branchSlug, string $token): View
    {
        $penerima = $this->resolvePenerima($branchSlug, $token);
        $penerima->refresh();

        return view('tagihan.public.show', [
            'penerima' => $penerima,
            'dendaSaatIni' => $penerima->denda_amount,
            'checkoutTimeoutMinutes' => (int) config('services.duitku.checkout_timeout_minutes', 10),
            'returnMessage' => match ($penerima->status) {
                TagihanPenerima::STATUS_LUNAS => ['type' => 'success', 'text' => 'Pembayaran berhasil. Terima kasih!'],
                TagihanPenerima::STATUS_KADALUARSA => ['type' => 'danger', 'text' => 'Waktu pembayaran sudah habis.'],
                TagihanPenerima::STATUS_DIBATALKAN => ['type' => 'danger', 'text' => 'Tagihan ini sudah dibatalkan.'],
                default => ['type' => 'info', 'text' => 'Pembayaran sedang diproses. Halaman ini akan otomatis menampilkan status lunas begitu terkonfirmasi.'],
            },
        ]);
    }

    /**
     * Lookup GANDA -- {token} (App\Models\TagihanPenerima::public_token)
     * adalah kunci akses sebenarnya, {branchSlug} cuma divalidasi cocok
     * dengan BranchOffice::slug baris ini supaya URL yang branch-nya
     * "diketik ulang" salah (tapi token-nya benar) tetap ditolak --
     * bukan dipakai sebagai bagian resolusi lookup itu sendiri.
     */
    private function resolvePenerima(string $branchSlug, string $token): TagihanPenerima
    {
        $penerima = TagihanPenerima::where('public_token', $token)
            ->with(['tagihan.category', 'pelanggan', 'branchOffice'])
            ->firstOrFail();

        abort_unless($penerima->branchOffice?->slug === $branchSlug, 404);

        // Kadaluarsa dievaluasi di sini juga (bukan cuma menunggu
        // App\Console\Commands\ProcessTagihanExpiry yang jalan tiap
        // menit) -- defense-in-depth sama pola dengan
        // DepositController::hasExpiredWindow().
        if ($penerima->status === TagihanPenerima::STATUS_BELUM_BAYAR && $this->hasExpiredWindow($penerima)) {
            $penerima->update(['status' => TagihanPenerima::STATUS_KADALUARSA]);
        }

        return $penerima;
    }

    private function hasExpiredWindow(TagihanPenerima $penerima): bool
    {
        return $penerima->expires_at !== null && $penerima->expires_at->isPast();
    }

    private function rejectedView(TagihanPenerima $penerima, string $message, bool $retry = false): View
    {
        return view('tagihan.public.show', [
            'penerima' => $penerima->fresh(['tagihan.category', 'pelanggan', 'branchOffice']),
            'dendaSaatIni' => $penerima->denda_amount,
            'checkoutTimeoutMinutes' => (int) config('services.duitku.checkout_timeout_minutes', 10),
            'returnMessage' => ['type' => 'danger', 'text' => $message],
        ]);
    }
}
