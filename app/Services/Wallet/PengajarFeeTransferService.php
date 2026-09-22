<?php

namespace App\Services\Wallet;

use App\Models\BranchOffice;
use App\Models\JadwalKelas;
use App\Models\PengajarFeeTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fitur "Transfer Fee" bulanan (diskusi 22 September 2026) -- membayar
 * fee pengajar dari saldo terkumpul Wallet satu BranchOffice, dihitung
 * dari data yang SUDAH ADA di Jadwal (App\Models\JadwalKelas::
 * feePengajar(), snapshot harga_sesi × persentase_pengajar per sesi),
 * TIDAK dari App\Models\TagihanCategory manapun -- lihat diskusi 22
 * September 2026 kenapa keduanya sengaja tidak dipaksa nyambung 1-1;
 * Wallet Branch diperlakukan sebagai kolam bersama (App\Http\
 * Controllers\Tagihan\TagihanDuitkuCallbackController yang mengisinya).
 *
 * preview() murni baca, TIDAK mengunci apa pun -- aman dipanggil
 * berkali-kali (tampilan awal halaman, refresh, dst). execute() yang
 * benar-benar memindahkan uang, HARUS selalu dalam satu DB::transaction
 * dengan lockForUpdate() di baris JadwalKelas yang ikut, supaya dua klik
 * "Transfer Fee" nyaris bersamaan untuk periode yang sama tidak
 * membayar sesi yang sama dua kali (lihat juga unique constraint
 * (branch_office_id, periode_year, periode_month) di migration
 * pengajar_fee_transfers sebagai lapis kedua).
 *
 * Kalau di tengah loop pengajar manapun App\Services\Wallet\
 * WalletLedgerService::debit() gagal (saldo Branch ternyata kurang),
 * RuntimeException itu membatalkan SELURUH transaction -- keputusan
 * user 22 September 2026: "seluruh batch itu ditahan dulu (tidak
 * sebagian-sebagian)", jadi TIDAK ada logika catch-dan-lanjut di sini.
 */
class PengajarFeeTransferService
{
    /**
     * @return array{sesi_count: int, pengajar_count: int, total: float, breakdown: array<int, array{pengajar_id: ?string, pengajar_name: string, sesi_count: int, total: float}>}
     */
    public function preview(BranchOffice $branch, int $year, int $month): array
    {
        $rows = $this->eligibleSesiQuery($branch, $year, $month)->with('pengajar:id,name')->get();

        $breakdown = $this->groupByPengajar($rows)
            ->filter(fn (array $item) => $item['total'] > 0)
            ->sortByDesc('total')
            ->values();

        return [
            'sesi_count' => $rows->count(),
            'pengajar_count' => $breakdown->count(),
            'total' => round($breakdown->sum('total'), 2),
            'breakdown' => $breakdown->all(),
        ];
    }

    /**
     * @throws RuntimeException kalau tidak ada fee yang perlu dibayarkan
     *                           untuk periode ini, atau saldo Branch
     *                           kurang (dilempar dari WalletLedgerService).
     */
    public function execute(BranchOffice $branch, int $year, int $month, User $executedBy): PengajarFeeTransfer
    {
        return DB::transaction(function () use ($branch, $year, $month, $executedBy) {
            $rows = $this->eligibleSesiQuery($branch, $year, $month)
                ->with('pengajar')
                ->lockForUpdate()
                ->get();

            $byPengajar = $this->groupByPengajar($rows)->filter(fn (array $item) => $item['total'] > 0);

            if ($byPengajar->isEmpty()) {
                throw new RuntimeException('Tidak ada fee pengajar yang perlu dibayarkan untuk periode ini.');
            }

            $branchWallet = WalletProvisioningService::forBranch($branch->id);

            $transfer = PengajarFeeTransfer::create([
                'company_id' => $branch->company_id,
                'branch_office_id' => $branch->id,
                'wallet_id' => $branchWallet->id,
                'periode_year' => $year,
                'periode_month' => $month,
                'total_debited' => round($byPengajar->sum('total'), 2),
                'pengajar_count' => $byPengajar->count(),
                'sesi_count' => $rows->count(),
                'executed_by' => $executedBy->id,
            ]);

            foreach ($byPengajar as $item) {
                $pengajarWallet = WalletProvisioningService::forUser($item['pengajar']);

                WalletLedgerService::debit(
                    $branchWallet,
                    $item['total'],
                    PengajarFeeTransfer::class,
                    $transfer->id,
                    "Transfer fee pengajar {$item['pengajar']->name} — periode {$transfer->periodeLabel()}",
                    $executedBy->id,
                    'PENGAJAR_FEE',
                );

                WalletLedgerService::credit(
                    $pengajarWallet,
                    $item['total'],
                    PengajarFeeTransfer::class,
                    $transfer->id,
                    "Fee mengajar periode {$transfer->periodeLabel()}",
                    $executedBy->id,
                    'PENGAJAR_FEE',
                );
            }

            // Tandai SEMUA sesi eligible (termasuk yang total fee-nya 0,
            // mis. harga_sesi belum diisi) supaya tidak nyangkut terus
            // muncul di preview/eksekusi berikutnya walau tidak dibayar.
            JadwalKelas::whereIn('id', $rows->pluck('id'))->update(['fee_transfer_id' => $transfer->id]);

            return $transfer->fresh();
        });
    }

    /**
     * @return Collection<int, array{pengajar: ?User, pengajar_id: ?string, pengajar_name: string, sesi_count: int, total: float}>
     */
    private function groupByPengajar(Collection $rows): Collection
    {
        return $rows->groupBy('pengajar_id')->map(function (Collection $sesiList) {
            $pengajar = $sesiList->first()->pengajar;

            return [
                'pengajar' => $pengajar,
                'pengajar_id' => $pengajar?->id,
                'pengajar_name' => $pengajar?->name ?? '(akun pengajar tidak ditemukan)',
                'sesi_count' => $sesiList->count(),
                'total' => round($sesiList->sum(fn (JadwalKelas $s) => $s->feePengajar()), 2),
            ];
        })->filter(fn (array $item) => $item['pengajar'] !== null)->values();
    }

    /**
     * Sesi yang BERHAK dibayarkan untuk satu branch + periode: belum
     * pernah termasuk transfer manapun (fee_transfer_id null), sesinya
     * aktif (bukan yang dibatalkan), dan attendance_status ada di
     * JadwalKelas::ATTENDANCE_TETAP_DIBAYAR (hadir, atau tidak_hadir
     * tanpa keterangan — keduanya tetap membayar pengajar penuh; izin/
     * sakit TIDAK, karena berhak dapat sesi pengganti terpisah, lihat
     * docblock JadwalKelas::pengganti_dari_sesi_id).
     */
    private function eligibleSesiQuery(BranchOffice $branch, int $year, int $month)
    {
        return JadwalKelas::query()
            ->where('branch_office_id', $branch->id)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->whereNull('fee_transfer_id')
            ->whereNotNull('pengajar_id')
            ->whereIn('attendance_status', JadwalKelas::ATTENDANCE_TETAP_DIBAYAR)
            ->whereYear('start_time', $year)
            ->whereMonth('start_time', $month);
    }
}
