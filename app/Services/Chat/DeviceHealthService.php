<?php

namespace App\Services\Chat;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scores a WhatsApp device's "health" — how likely it is to be flagged/
 * banned, or already unstable — from signals actually available to this
 * app. This is an UNOFFICIAL WhatsApp connection (g_backend uses
 * whatsmeow, the same multi-device-web protocol WhatsApp Web itself
 * uses, not Meta's official Business Cloud API), so there is no real
 * "quality rating" from Meta to read — everything here is a proxy signal
 * built from this app's own data instead:
 *
 *   - Connection stability: how often the device has been logged out /
 *     dropped / failed to reconnect recently (wa_device_histories, owned
 *     by the Go backend — see App\Services\Chat\DeviceDirectory's
 *     docblock for the shared-database reasoning).
 *   - Send reliability: what fraction of its recent broadcast sends
 *     actually succeeded (wa_message_schedule_logs, from Fitur #2).
 *   - Whether it's connected right now at all.
 *
 * A `logged_out` event is the single strongest signal here — WhatsApp
 * itself only sends that when a session was revoked (banned, manually
 * unlinked from the phone, or logged out from another device), which is
 * exactly the outcome every other anti-ban measure in this app
 * (App\Services\Chat\BroadcastThrottleService, BroadcastOptOutService,
 * DispatchDueWaMessageSchedules' stagger) exists to prevent.
 */
class DeviceHealthService
{
    /** How far back connection history / send outcomes are looked at. */
    public const WINDOW_DAYS = 7;

    private const SCORE_MAX = 100;

    // Point deductions — see assess()'s docblock for how these combine.
    private const PENALTY_DISCONNECTED_NOW = 30;

    private const PENALTY_PER_LOGGED_OUT = 25;

    private const PENALTY_PER_LOGGED_OUT_CAP = 75;

    private const PENALTY_PER_RECONNECT_FAILED = 5;

    private const PENALTY_RECONNECT_FAILED_CAP = 20;

    /** Beyond this many disconnect events in the window, each extra one costs a point (capped). */
    private const NORMAL_DISCONNECT_THRESHOLD = 5;

    private const PENALTY_EXCESS_DISCONNECT_CAP = 15;

    public function __construct(protected DeviceDirectory $devices)
    {
    }

    /**
     * @return array{
     *     device_id: string,
     *     status: string,
     *     score: int,
     *     label: string,
     *     signals: array{
     *         logged_out_count: int,
     *         disconnected_count: int,
     *         reconnect_failed_count: int,
     *         send_failure_rate: ?float,
     *         send_sample_size: int,
     *     },
     * }
     */
    public function assess(string $deviceId): array
    {
        $status = $this->currentStatus($deviceId);
        $eventCounts = $this->recentEventCounts($deviceId);
        $sendStats = $this->recentSendStats($deviceId);

        $score = self::SCORE_MAX;

        if ($status !== 'connected') {
            $score -= self::PENALTY_DISCONNECTED_NOW;
        }

        $score -= min(self::PENALTY_PER_LOGGED_OUT_CAP, $eventCounts['logged_out'] * self::PENALTY_PER_LOGGED_OUT);
        $score -= min(self::PENALTY_RECONNECT_FAILED_CAP, $eventCounts['reconnect_failed'] * self::PENALTY_PER_RECONNECT_FAILED);

        $excessDisconnects = max(0, $eventCounts['disconnected'] - self::NORMAL_DISCONNECT_THRESHOLD);
        $score -= min(self::PENALTY_EXCESS_DISCONNECT_CAP, $excessDisconnects);

        if ($sendStats['sample_size'] > 0) {
            // A failure rate over 10% costs a point per percentage point
            // above that, capped at 25 — a device failing 1-in-10 sends
            // is already worth flagging, and one failing most of its
            // sends shouldn't need a huge sample to be marked risky.
            $failureRatePercent = $sendStats['failure_rate'] * 100;
            $score -= min(25, max(0, $failureRatePercent - 10));
        }

        $score = (int) max(0, min(self::SCORE_MAX, round($score)));

        return [
            'device_id' => $deviceId,
            'status' => $status,
            'score' => $score,
            'label' => $this->label($score),
            'signals' => [
                'logged_out_count' => $eventCounts['logged_out'],
                'disconnected_count' => $eventCounts['disconnected'],
                'reconnect_failed_count' => $eventCounts['reconnect_failed'],
                'send_failure_rate' => $sendStats['sample_size'] > 0 ? round($sendStats['failure_rate'] * 100, 1) : null,
                'send_sample_size' => $sendStats['sample_size'],
            ],
        ];
    }

    /**
     * Every device in a company (optionally one branch), ranked
     * best-health-first — the practical "which number should I use for
     * this broadcast" answer App\Services\Chat\BroadcastThrottleService
     * enforces a hard ceiling on but can't tell you which of several
     * eligible devices is the SAFEST choice today. Also surfaces each
     * device's current load (sends in the last hour) so two
     * similarly-healthy devices break ties toward whichever is less busy
     * right now.
     *
     * @return Collection<int, array{device_id: string, phone_number: ?string, status: string, score: int, label: string, recent_send_count: int, signals: array}>
     */
    public function rankDevicesForBroadcast(string $companyId, ?string $branchOfficeId = null): Collection
    {
        $devices = $this->devices->devicesForCompany($companyId, $branchOfficeId);

        return $devices
            ->map(function ($device) {
                $assessment = $this->assess($device->id);

                return array_merge($assessment, [
                    'phone_number' => $device->phone_number,
                    'recent_send_count' => $this->sendCountLastHour($device->id),
                ]);
            })
            ->sortBy([
                ['score', 'desc'],
                ['recent_send_count', 'asc'],
            ])
            ->values();
    }

    private function currentStatus(string $deviceId): string
    {
        $status = DB::table('wa_devices')->where('id', $deviceId)->value('status');

        return $status ?? 'disconnected';
    }

    /**
     * @return array{logged_out: int, disconnected: int, reconnect_failed: int}
     */
    private function recentEventCounts(string $deviceId): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $counts = DB::table('wa_device_histories')
            ->where('device_id', $deviceId)
            ->where('created_at', '>=', $since)
            ->whereIn('event', ['logged_out', 'disconnected', 'reconnect_failed'])
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        return [
            'logged_out' => (int) ($counts->get('logged_out') ?? 0),
            'disconnected' => (int) ($counts->get('disconnected') ?? 0),
            'reconnect_failed' => (int) ($counts->get('reconnect_failed') ?? 0),
        ];
    }

    /**
     * @return array{failure_rate: float, sample_size: int}
     */
    private function recentSendStats(string $deviceId): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $counts = DB::table('wa_message_schedule_logs')
            ->join('wa_message_schedules', 'wa_message_schedules.id', '=', 'wa_message_schedule_logs.wa_message_schedule_id')
            ->where('wa_message_schedules.device_id', $deviceId)
            ->where('wa_message_schedule_logs.send_date', '>=', $since->toDateString())
            ->whereIn('wa_message_schedule_logs.status', ['sent', 'delivered', 'read', 'failed'])
            ->selectRaw('wa_message_schedule_logs.status as status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('wa_message_schedule_logs.status')
            ->pluck('total', 'status');

        $succeeded = (int) ($counts->get('sent', 0) + $counts->get('delivered', 0) + $counts->get('read', 0));
        $failed = (int) $counts->get('failed', 0);
        $sample = $succeeded + $failed;

        return [
            'failure_rate' => $sample > 0 ? $failed / $sample : 0.0,
            'sample_size' => $sample,
        ];
    }

    /**
     * "Load" here means genuine send attempts only — 'skipped' (opt-out,
     * see App\Services\Chat\BroadcastOptOutService) never actually
     * touched the device/network, so counting it as recent activity
     * would make a device with a lot of opt-outs look busier than it
     * really is for the tie-break in rankDevicesForBroadcast() above.
     */
    private function sendCountLastHour(string $deviceId): int
    {
        return (int) DB::table('wa_message_schedule_logs')
            ->join('wa_message_schedules', 'wa_message_schedules.id', '=', 'wa_message_schedule_logs.wa_message_schedule_id')
            ->where('wa_message_schedules.device_id', $deviceId)
            ->whereIn('wa_message_schedule_logs.status', ['sent', 'delivered', 'read', 'failed'])
            ->where('wa_message_schedule_logs.updated_at', '>=', now()->subHour())
            ->count();
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Sehat',
            $score >= 50 => 'Perlu Perhatian',
            default => 'Berisiko',
        };
    }
}
