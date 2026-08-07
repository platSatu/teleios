<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only log of every Duitku payment callback — both real inbound
 * ones (see User\Deposit\DuitkuCallbackController, which now stamps a
 * specific event_type per outcome instead of one generic string for
 * everything) and the synthetic PAYMENT_EXPIRED rows written by
 * App\Console\Commands\ProcessDepositExpiry when a PENDING deposit's
 * payment window closes without Duitku ever sending a callback at all.
 *
 * All sourced from payment_webhooks — a table that existed before this
 * (see its migration's docblock) purely as a raw audit trail, but had
 * no superadmin UI reading it anywhere until now. Built specifically so
 * UAT has one place to see each callback scenario called out by the
 * request: berhasil (PAYMENT_SUCCESS), failed (PAYMENT_FAILED), gagal
 * (PAYMENT_ERROR — our own processing failures: bad signature, no
 * matching deposit, unhandled exception), and expired (PAYMENT_EXPIRED)
 * — plus PAYMENT_PENDING and PAYMENT_IGNORED_DUPLICATE for completeness.
 */
class PaymentWebhookController extends Controller
{
    private const EVENT_TYPES = [
        'PAYMENT_SUCCESS',
        'PAYMENT_PENDING',
        'PAYMENT_FAILED',
        'PAYMENT_EXPIRED',
        'PAYMENT_ERROR',
        'PAYMENT_IGNORED_DUPLICATE',
        'PAYMENT_CALLBACK_RECEIVED',
    ];

    public function index(Request $request): View
    {
        $eventType = $request->query('event_type');

        $logs = PaymentWebhook::query()
            ->when(
                $eventType && in_array($eventType, self::EVENT_TYPES, true),
                fn ($query) => $query->where('event_type', $eventType)
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        // reference_id/reference_type is a loose polymorphic pointer
        // (no real relation defined on PaymentWebhook), always at
        // Deposit for now — one extra query here beats an N+1 lookup
        // per row in the view.
        $depositIds = $logs->getCollection()
            ->where('reference_type', Deposit::class)
            ->pluck('reference_id')
            ->filter()
            ->unique();

        $deposits = $depositIds->isEmpty()
            ? collect()
            : Deposit::whereIn('id', $depositIds)->get()->keyBy('id');

        $logs->getCollection()->transform(function ($log) use ($deposits) {
            $log->related_deposit = $deposits->get($log->reference_id);

            return $log;
        });

        // One query, grouped, instead of one COUNT per badge — keeps
        // the summary row cheap regardless of how many event_types
        // exist.
        $counts = PaymentWebhook::query()
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return view('superadmin.payment-webhooks.index', [
            'logs' => $logs,
            'counts' => $counts,
            'eventTypes' => self::EVENT_TYPES,
            'activeEventType' => $eventType,
        ]);
    }

    public function show(string $id): View
    {
        $log = PaymentWebhook::findOrFail($id);

        $log->related_deposit = ($log->reference_type === Deposit::class && $log->reference_id)
            ? Deposit::find($log->reference_id)
            : null;

        return view('superadmin.payment-webhooks.show', compact('log'));
    }
}
