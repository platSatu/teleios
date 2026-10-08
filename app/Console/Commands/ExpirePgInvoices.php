<?php

namespace App\Console\Commands;

use App\Models\PgInvoice;
use App\Services\PaymentGateway\PgPaymentService;
use Illuminate\Console\Command;

/** Tiap menit: invoice Payment Gateway yang lewat batas waktu -> expired (+ webhook). */
class ExpirePgInvoices extends Command
{
    protected $signature = 'pg:expire-invoices';

    protected $description = 'Tandai invoice Payment Gateway yang kedaluwarsa';

    public function handle(PgPaymentService $payments): int
    {
        $count = 0;

        PgInvoice::where('status', PgInvoice::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->select(['id'])
            ->chunkById(500, function ($rows) use ($payments, &$count) {
                foreach ($rows as $invoice) {
                    $count += $payments->close($invoice, PgInvoice::STATUS_EXPIRED) ? 1 : 0;
                }
            });

        $this->info("{$count} invoice kedaluwarsa.");

        return self::SUCCESS;
    }
}
