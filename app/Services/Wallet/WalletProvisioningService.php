<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;

/**
 * Satu-satunya tempat yang boleh membuat baris App\Models\Wallet baru
 * milik BranchOffice — menegakkan invariant "harus persis satu dari
 * user_id/branch_office_id yang terisi" (lihat docblock migration
 * add_branch_office_id_to_wallets_table.php) di level aplikasi, karena
 * TIDAK ada CHECK constraint di level database untuk itu.
 *
 * Wallet milik User TIDAK butuh method serupa untuk dibuat — setiap
 * User sudah otomatis dapat Wallet-nya sendiri lewat User::boot()'s
 * `created` event sejak awal. forUser() di sini murni jaga-jaga (data
 * lama dari sebelum trait itu ada, atau race langka saat registrasi),
 * bukan jalur pembuatan Wallet User yang normal.
 */
class WalletProvisioningService
{
    /**
     * Get-or-create Wallet milik satu BranchOffice (mata uang IDR,
     * satu-satunya yang dipakai aplikasi ini sekarang — lihat
     * Wallet::$fillable). Dipanggil dari App\Http\Controllers\Tagihan\
     * TagihanDuitkuCallbackController tiap kali TagihanPenerima lunas,
     * jadi ini jalur "wallet pertama kali muncul" untuk sebagian besar
     * branch — bukan sesuatu yang perlu di-seed duluan.
     */
    public static function forBranch(string $branchOfficeId): Wallet
    {
        $wallet = Wallet::where('branch_office_id', $branchOfficeId)
            ->where('currency', 'IDR')
            ->first();

        if ($wallet) {
            return $wallet;
        }

        try {
            return Wallet::create([
                'branch_office_id' => $branchOfficeId,
                'user_id' => null,
                'currency' => 'IDR',
                'balance' => 0,
                'status' => 'active',
            ]);
        } catch (QueryException $e) {
            // Race: dua callback Duitku untuk branch yang sama, keduanya
            // sama-sama tidak menemukan Wallet di atas, keduanya coba
            // create() nyaris bersamaan — yang kedua kena unique
            // constraint (branch_office_id, currency). Bukan error
            // sungguhan, cukup ambil baris yang barusan dibuat oleh yang
            // pertama daripada menggagalkan callback pembayaran ini.
            $wallet = Wallet::where('branch_office_id', $branchOfficeId)
                ->where('currency', 'IDR')
                ->first();

            if (! $wallet) {
                throw $e;
            }

            return $wallet;
        }
    }

    /**
     * Lihat docblock class di atas — fallback jaga-jaga, bukan jalur
     * normal (yang normal ada di User::boot()).
     */
    public static function forUser(User $user): Wallet
    {
        return $user->wallet ?? Wallet::create([
            'user_id' => $user->id,
            'currency' => 'IDR',
            'balance' => 0,
            'status' => 'active',
        ]);
    }
}
