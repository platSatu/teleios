<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Riwayat Saldo" -- level PERSONAL (pengajar, reseller, atau owner
 * company yang login sebagai user biasa; semuanya cuma satu Wallet
 * per User, lihat App\Models\User::boot()). Saldo-nya sendiri sudah
 * tampil di dropdown profil header sejak awal (Auth::user()->wallet);
 * halaman ini cuma menambahkan histori LENGKAP (gabungan semua
 * transaction_type -- top up, transfer masuk/keluar, komisi referral,
 * fee mengajar, tarik saldo -- lihat App\Services\Wallet\
 * WalletLedgerService) yang sebelumnya tersebar di halaman
 * masing-masing fitur (Transfer Saldo, Tarik Saldo) tanpa satu tempat
 * gabungan.
 *
 * Bagian 6/6 rencana fitur disbursement Duitku (lihat App\Http\
 * Controllers\Keuangan\SaldoDashboardController untuk versi level
 * Branch/Company).
 */
class WalletDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $histori = $wallet
            ? LedgerEntry::with('transaction')
                ->where('wallet_id', $wallet->id)
                ->latest()
                ->paginate(20)
            : null;

        return view('wallet.dashboard.index', compact('wallet', 'histori'));
    }
}
