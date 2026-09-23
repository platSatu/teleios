<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PaymentWebhook;
use App\Models\TagihanPenerima;
use App\Models\TransactionStatusHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Meniru App\Console\Commands\ProcessDepositExpiry persis (lihat
 * docblock-nya untuk alasan minute-granular & kenapa Duitku sendiri
 * tidak pernah push "expired" callback) -- tapi untuk
 * App\Models\TagihanPenerima, bukan App\Models\Deposit.
 *
 * Sengaja TIDAK ada bagian "kirim reminder" di sini seperti
 * ProcessDepositExpiry -- itu H-15-menit-sebelum-expired (reminder
 * "segera bayar" untuk invoice yang SEDANG diproses Duitku).
 * App\Models\TagihanReminderRule/TagihanReminderLog adalah pengingat
 * yang BEDA KONSEP (H-7/H-3/H-1/Hari-H dari due_date Tagihan, sebelum
 * invoice Duitku bahkan pernah dibuat) -- sekarang ditangani command
 * terpisah App\Console\Commands\DispatchDueTagihanReminders + App\Jobs\
 * SendTagihanReminder (23 September 2026, audit kesiapan launch;
 * sebelumnya sengaja ditahan per instruksi "jangan sambungkan dulu ya
 * dengan whatsapp", sudah dicabut user).
 */
class ProcessTagihanExpiry extends Command
{
    protected $signature = 'tagihan:process-expiry';

    protected $description = 'Expire TagihanPenerima (invoice) belum_bayar yang jendela pembayaran Duitku-nya sudah lewat';

    public function handle(): int
    {
        $expired = $this->expireOverdueTagihanPenerima();

        $this->info("TagihanPenerima expired: {$expired}.");

        return self::SUCCESS;
    }

    private function expireOverdueTagihanPenerima(): int
    {
        $now = now();

        $candidates = TagihanPenerima::query()
            ->where('status', TagihanPenerima::STATUS_BELUM_BAYAR)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $expiredCount = 0;

        foreach ($candidates as $penerima) {
            try {
                $result = DB::transaction(function () use ($penerima) {
                    $locked = TagihanPenerima::whereKey($penerima->id)->lockForUpdate()->first();

                    if (! $locked || $locked->status !== TagihanPenerima::STATUS_BELUM_BAYAR) {
                        return null;
                    }

                    $oldStatus = $locked->status;

                    $locked->update(['status' => TagihanPenerima::STATUS_KADALUARSA]);

                    TransactionStatusHistory::create([
                        'entity_type' => TagihanPenerima::class,
                        'entity_id' => $locked->id,
                        'old_status' => $oldStatus,
                        'new_status' => TagihanPenerima::STATUS_KADALUARSA,
                        'changed_by' => null,
                    ]);

                    AuditLog::create([
                        'actor_type' => 'SYSTEM',
                        'actor_id' => null,
                        'action' => 'TAGIHAN_PENERIMA_EXPIRED',
                        'entity_type' => 'TagihanPenerima',
                        'entity_id' => $locked->id,
                        'old_value' => ['status' => $oldStatus],
                        'new_value' => ['status' => TagihanPenerima::STATUS_KADALUARSA],
                        'ip_address' => null,
                        'user_agent' => 'tagihan:process-expiry (scheduled command)',
                        'created_at' => now(),
                    ]);

                    PaymentWebhook::create([
                        'provider' => 'DUITKU',
                        'event_type' => 'TAGIHAN_PAYMENT_EXPIRED',
                        'signature' => null,
                        'payload' => [
                            'source' => 'tagihan:process-expiry',
                            'merchantOrderId' => $locked->order_number,
                            'tagihan_penerima_id' => $locked->id,
                            'amount' => (string) $locked->amount,
                            'status_before' => $oldStatus,
                            'status_after' => TagihanPenerima::STATUS_KADALUARSA,
                            'expires_at' => optional($locked->expires_at)->toIso8601String(),
                            'expired_at' => now()->toIso8601String(),
                        ],
                        'processed' => true,
                        'processed_at' => now(),
                        'reference_type' => TagihanPenerima::class,
                        'reference_id' => $locked->id,
                    ]);

                    return $locked;
                });

                if ($result) {
                    $expiredCount++;
                }
            } catch (Throwable $e) {
                Log::error('tagihan:process-expiry: failed to expire tagihan_penerima', [
                    'tagihan_penerima_id' => $penerima->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expiredCount;
    }
}
