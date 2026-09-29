<?php

namespace App\Console\Commands;

use App\Services\Referral\ReferralService;
use Illuminate\Console\Command;

/**
 * Cairkan komisi referral yang masa tahan/komplainnya sudah lewat ke wallet
 * pemilik kode. Dijadwalkan tiap jam (bootstrap/app.php); aman diulang
 * karena setiap komisi dikunci & dicek statusnya sebelum dicairkan.
 */
class ReleaseReferralCommissions extends Command
{
    protected $signature = 'referral:release-commissions';

    protected $description = 'Cairkan komisi referral yang sudah melewati masa tahan';

    public function handle(ReferralService $referrals): int
    {
        $this->info('Komisi dicairkan: '.$referrals->releaseDue());

        return self::SUCCESS;
    }
}
