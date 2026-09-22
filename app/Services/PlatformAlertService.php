<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends operational alerts (system health + new-registration notices) to
 * the platform OWNER over WhatsApp — CLAUDE.md checklist item #10 ("kalau
 * g_backend down, atau kuota/error rate naik drastis, ada yang tahu
 * sebelum customer complain"). Deliberately separate from anything
 * company-facing: every WhatsApp device in this app belongs to a company
 * (see App\Models\WaApiKey's docblock — no such thing as a "platform-
 * owned device"), so rather than borrow a CUSTOMER's device to relay an
 * internal alert (which would leave a stray message in THEIR chat
 * history — a real concern raised while building this), every alert here
 * is sent from-and-to the platform owner's OWN number/device (Ensosmed,
 * see the constants below) — a WhatsApp "message yourself" note.
 *
 * Two kinds of alert, each with its own gating:
 *   - Platform health (recordPackageLimitBreach()/recordHttp500() +
 *     checkAndAlert(), driven by App\Console\Commands\PlatformHealthAlert
 *     every minute) — a ROLLING 15-MINUTE, ACROSS-ALL-COMPANIES count of
 *     "signs something's systemically wrong", gated by a 30-minute
 *     cooldown so one sustained incident doesn't flood WhatsApp with one
 *     alert per check. Counters live in cache (Cache::increment() on a
 *     per-minute bucket key, TTL 20 min) rather than a new DB table or
 *     grepping storage/logs/laravel.log (investigated first — logging at
 *     each PackageLimitExceededException catch site turned out
 *     inconsistent/absent across ~8 different callers, so this counts at
 *     the single throw-site choke point in App\Services\
 *     PackageLimitService instead, which every caller goes through
 *     regardless of how — or whether — it separately logs).
 *   - New registration (sendRegistrationAlert()) — fires immediately, no
 *     threshold/cooldown, since a signup is a single discrete business
 *     event the owner wants to know about in real time, not an anomaly to
 *     rate-limit.
 *
 * Every send here is wrapped so a WhatsApp failure (device disconnected,
 * g_backend down — plausibly true at the exact moment a health alert
 * needs to go out) never bubbles up into the caller (a scheduled command,
 * or someone's registration request) — it's Log::warning()'d instead.
 */
class PlatformAlertService
{
    /**
     * Ensosmed — the platform owner's OWN company in this system, not a
     * customer's. See this checklist item's discussion: using any
     * customer's device for an internal alert was explicitly ruled out.
     */
    private const ALERT_COMPANY_ID = '6d6b677f-91a4-4b46-bb81-27ddca028c45';

    private const ALERT_DEVICE_ID = '1b560813-08ee-440b-9c2e-c2181312a01c';

    /** Same number as the sending device — a WhatsApp "message yourself". */
    private const ALERT_TARGET_JID = '6281286800080@s.whatsapp.net';

    private const PKG_LIMIT_THRESHOLD = 10;

    private const HTTP_500_THRESHOLD = 20;

    private const WINDOW_MINUTES = 15;

    private const COOLDOWN_MINUTES = 30;

    private const BUCKET_TTL_MINUTES = 20;

    public function __construct(
        private readonly InboxService $inbox,
        private readonly SystemJwtService $jwtService,
    ) {}

    /**
     * Call this at every App\Services\PackageLimitService throw site for
     * PackageLimitExceededException, resolved via the app() helper right
     * there (NOT constructor-injected into PackageLimitService — that
     * would create a circular dependency: PlatformAlertService needs
     * InboxService, which itself needs PackageLimitService).
     */
    public function recordPackageLimitBreach(): void
    {
        $this->incrementBucket('pkg_limit');
    }

    /** Call this from bootstrap/app.php's exception reporting for any 5xx. */
    public function recordHttp500(): void
    {
        $this->incrementBucket('http500');
    }

    private function incrementBucket(string $type): void
    {
        $key = 'platform-alert:'.$type.':'.now()->format('YmdHi');
        Cache::add($key, 0, now()->addMinutes(self::BUCKET_TTL_MINUTES));
        Cache::increment($key);
    }

    private function sumLastMinutes(string $type, int $minutes): int
    {
        $sum = 0;
        $now = now();

        for ($i = 0; $i < $minutes; $i++) {
            $key = 'platform-alert:'.$type.':'.$now->copy()->subMinutes($i)->format('YmdHi');
            $sum += (int) Cache::get($key, 0);
        }

        return $sum;
    }

    /**
     * Called every minute by App\Console\Commands\PlatformHealthAlert —
     * sums both counters over the last 15 minutes, across every company,
     * and sends ONE alert if either threshold is breached, gated by a
     * 30-minute cooldown (Cache::add() — the first call to breach a
     * threshold "claims" the cooldown key; every other call within the
     * window is a no-op) so a sustained incident doesn't flood WhatsApp.
     */
    public function checkAndAlert(): void
    {
        $pkgLimitCount = $this->sumLastMinutes('pkg_limit', self::WINDOW_MINUTES);
        $http500Count = $this->sumLastMinutes('http500', self::WINDOW_MINUTES);

        $breached = [];

        if ($pkgLimitCount > self::PKG_LIMIT_THRESHOLD) {
            $breached[] = "Package/kuota habis: {$pkgLimitCount}x dalam ".self::WINDOW_MINUTES.' menit terakhir';
        }

        if ($http500Count > self::HTTP_500_THRESHOLD) {
            $breached[] = "Error 500: {$http500Count}x dalam ".self::WINDOW_MINUTES.' menit terakhir';
        }

        if (empty($breached)) {
            return;
        }

        if (! Cache::add('platform-alert:cooldown', true, now()->addMinutes(self::COOLDOWN_MINUTES))) {
            // Sudah ada alert terkirim dalam 30 menit terakhir untuk
            // insiden yang (kemungkinan) sama — jangan spam.
            return;
        }

        $message = "⚠️ *Platform Health Alert*\n\n".implode("\n", $breached)."\n\nCek dashboard/log untuk detail lebih lanjut.";

        $this->send($message);
    }

    /**
     * Fires immediately on a new user registration (App\Http\Controllers\
     * Auth\AuthController::register()) — no threshold/cooldown, since this
     * is a discrete business event, not an anomaly to rate-limit. Wrapped
     * in try/catch by send() below, so a WhatsApp hiccup never breaks
     * someone's registration request.
     */
    public function sendRegistrationAlert(string $name, string $email): void
    {
        $message = "🎉 *Registrasi baru*\n\nNama: {$name}\nEmail: {$email}\nWaktu: ".now()->format('d M Y H:i').' WIB';

        $this->send($message);
    }

    private function send(string $message): void
    {
        try {
            $company = Company::find(self::ALERT_COMPANY_ID);
            $owner = $company?->user;

            if (! $owner) {
                Log::warning('PlatformAlertService: alert company/owner not found, alert not sent', [
                    'company_id' => self::ALERT_COMPANY_ID,
                ]);

                return;
            }

            $jwt = $this->jwtService->mintFor($owner);

            $this->inbox->send($jwt, self::ALERT_DEVICE_ID, self::ALERT_TARGET_JID, $message);
        } catch (Throwable $e) {
            Log::warning('PlatformAlertService: failed to send alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
