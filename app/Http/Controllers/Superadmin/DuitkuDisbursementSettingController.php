<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DuitkuDisbursementSetting;
use App\Services\Payment\DuitkuDisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Pengaturan kredensial Duitku Disbursement (Tarik Saldo) -- User ID,
 * Email, Secret Key, terpisah Sandbox/Production, plus mode aktif.
 * Sama pola dengan DuitkuSettingController: Secret Key tidak pernah
 * ditampilkan ulang, kolom kosong = pertahankan key lama.
 *
 * Kredensial ini bisa mengirim uang keluar, jadi menyimpan wajib PIN
 * transaksi dan dicatat di AuditLog (tanpa isi Secret Key).
 */
class DuitkuDisbursementSettingController extends Controller
{
    use VerifiesTransactionPin;

    private const FIELDS = ['withdrawal_fee', 'sandbox_user_id', 'sandbox_email', 'production_user_id', 'production_email'];

    public function edit(): View
    {
        return view('superadmin.duitku-disbursement-setting.edit', ['setting' => DuitkuDisbursementSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(DuitkuDisbursementSetting::MODES)],
            // Biaya per tarik saldo, Rupiah bulat (dipotong dari jumlah yang ditarik user).
            'withdrawal_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sandbox_user_id' => ['nullable', 'digits_between:1,20'],
            'sandbox_email' => ['nullable', 'email', 'max:255'],
            'sandbox_secret_key' => ['nullable', 'string', 'max:500'],
            'production_user_id' => ['nullable', 'digits_between:1,20'],
            'production_email' => ['nullable', 'email', 'max:255'],
            'production_secret_key' => ['nullable', 'string', 'max:500'],
        ]);

        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        $setting = DuitkuDisbursementSetting::current();
        $before = $setting->only(['mode', ...self::FIELDS]);

        $setting->fill(collect($validated)->only(['mode', ...self::FIELDS])->all());

        foreach (['sandbox_secret_key', 'production_secret_key'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $setting->{$key} = $validated[$key];
            }
        }

        if (! $setting->isConfigured()) {
            return back()->withInput($request->except(['pin', 'sandbox_secret_key', 'production_secret_key']))
                ->withErrors(['mode' => 'User ID, Email, dan Secret Key '.ucfirst($setting->mode).' harus lengkap sebelum mode ini bisa diaktifkan.']);
        }

        $secretChanged = collect(['sandbox_secret_key', 'production_secret_key'])->filter(fn ($key) => $setting->isDirty($key))->values()->all();
        $setting->updated_by = $request->user()->id;
        $setting->save();

        // Daftar bank lama mungkin dari kredensial/mode sebelumnya.
        Cache::forget('duitku:disbursement-banks');

        AuditLog::record('duitku_disbursement_setting.update', DuitkuDisbursementSetting::class, $setting->id, $before, [
            ...$setting->only(['mode', ...self::FIELDS]),
            'secret_key_changed' => $secretChanged,
        ]);

        return redirect()->route('duitku-disbursement-setting.edit')->with('success', 'Pengaturan Duitku Disbursement berhasil disimpan. Klik "Tes Koneksi" untuk memastikan kredensialnya benar.');
    }

    /** Coba ambil daftar bank dengan kredensial mode aktif -- tidak memindahkan uang. */
    public function test(): RedirectResponse
    {
        try {
            $count = count(DuitkuDisbursementService::make()->listBanks());
        } catch (Throwable $e) {
            return back()->with('error', 'Tes koneksi gagal: '.$e->getMessage());
        }

        return back()->with('success', "Tes koneksi berhasil — {$count} bank tersedia.");
    }
}
