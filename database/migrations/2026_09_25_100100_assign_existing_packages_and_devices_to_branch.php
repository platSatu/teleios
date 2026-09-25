<?php

use App\Models\CompanyLimitUsage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paket sekarang berlaku PER BRANCH (alur Company -> Branch -> Paket).
 * Data lama belum punya branch, jadi supaya customer yang sudah aktif
 * tidak tiba-tiba terkunci setelah deploy, semuanya dipindahkan ke
 * BRANCH PERTAMA (paling awal dibuat) milik company-nya:
 *
 *   1. vouchers.company_id yang masih kosong diisi dari company milik
 *      user pemegang voucher (voucher lama / buatan superadmin).
 *   2. vouchers.branch_office_id yang masih kosong -> branch pertama.
 *   3. wa_devices.branch_office_id yang masih kosong (device yang dulu
 *      dibuat owner) -> branch pertama.
 *   4. company_limit_usages.branch_office_id yang masih kosong -> branch
 *      pertama, supaya pemakaian kuota yang sudah berjalan tidak ter-reset.
 *      Lewat model (bukan raw update) supaya usage_key ikut dihitung
 *      ulang oleh event saving di CompanyLimitUsage.
 *
 * Company yang BELUM punya branch sama sekali tidak disentuh -- owner-nya
 * perlu membuat branch dulu (sesuai alur baru), lalu memilih branch saat
 * redeem voucher.
 *
 * Idempotent: hanya menyentuh baris yang kolomnya masih NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // company_id -> id branch pertama (paling awal dibuat).
        $firstBranch = DB::table('branch_offices')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'company_id'])
            ->unique('company_id')
            ->pluck('id', 'company_id');

        // 1. company_id voucher dari company milik user pemegangnya.
        DB::table('vouchers')
            ->whereNull('company_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->select(['id', 'user_id'])
            ->chunk(200, function ($vouchers) {
                foreach ($vouchers as $voucher) {
                    $companyId = DB::table('companies')->where('user_id', $voucher->user_id)->value('id');

                    if ($companyId) {
                        DB::table('vouchers')->where('id', $voucher->id)->update(['company_id' => $companyId]);
                    }
                }
            });

        foreach ($firstBranch as $companyId => $branchId) {
            if (! $branchId) {
                continue;
            }

            // 2. voucher
            DB::table('vouchers')
                ->where('company_id', $companyId)
                ->whereNull('branch_office_id')
                ->update(['branch_office_id' => $branchId]);

            // 3. device WA (tabel milik backend Go, dicek dulu ada/tidak)
            if (Schema::hasTable('wa_devices') && Schema::hasColumn('wa_devices', 'branch_office_id')) {
                DB::table('wa_devices')
                    ->where('company_id', $companyId)
                    ->whereNull('branch_office_id')
                    ->update(['branch_office_id' => $branchId]);
            }

            // 4. counter kuota
            CompanyLimitUsage::where('company_id', $companyId)
                ->whereNull('branch_office_id')
                ->each(function (CompanyLimitUsage $usage) use ($branchId) {
                    $alreadyScoped = CompanyLimitUsage::where('company_id', $usage->company_id)
                        ->where('branch_office_id', $branchId)
                        ->where('limit_metric_id', $usage->limit_metric_id)
                        ->where('subscription_id', $usage->subscription_id)
                        ->exists();

                    if (! $alreadyScoped) {
                        $usage->branch_office_id = $branchId;
                        $usage->save();
                    }
                });
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-rollback: mengosongkan kembali branch_office_id
        // akan membuat semua paket tidak terbaca di branch mana pun.
    }
};
