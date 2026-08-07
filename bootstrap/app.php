<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Single execution engine for all 3 WaMessageSchedule types
        // (once / recurring / drip — "Pesan Terjadwal", "Forward &
        // Campaign Broadcast", and "Balasan Otomatis" were merged into
        // one entity, see App\Http\Controllers\Chat\
        // MessageScheduleController's docblock) — see
        // App\Console\Commands\DispatchDueWaMessageSchedules for what it
        // actually does. The old, separate wa-sequences:dispatch-due
        // (Balasan Otomatis' drip engine before the merge) has been
        // folded into this same command and retired.
        //
        // Laravel's scheduler only *decides* something is due once a
        // minute; it still needs a real OS-level trigger calling
        // `php artisan schedule:run` every minute, since XAMPP has no
        // cron of its own — on Windows that's a Task Scheduler entry, on
        // Linux a normal crontab line. withoutOverlapping() guards
        // against a slow run still executing when the next minute's tick
        // fires.
        $schedule->command('wa-schedules:dispatch-due')
            ->everyMinute()
            ->withoutOverlapping();

        // H-7/H-3/H-1/H0 package expiry reminder emails — see
        // App\Console\Commands\SendPackageExpiryReminders for the full
        // logic (idempotent per voucher+milestone, skips already-renewed
        // vouchers). Once a day is enough since milestones are computed
        // off calendar dates, not time-of-day.
        $schedule->command('package:send-expiry-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // Duitku payment-window reminders + expiry — see
        // App\Console\Commands\ProcessDepositExpiry. Minute-granular
        // (not daily, unlike the reminder above) since a Duitku
        // payment window is typically 60 minutes, not days — Duitku
        // itself never pushes an "expired" callback, so this command
        // is the only thing that ever moves a PENDING deposit to
        // EXPIRED.
        $schedule->command('deposit:process-expiry')
            ->everyMinute()
            ->withoutOverlapping();

        // H-1 (evening before) WhatsApp reminders for tomorrow's
        // jadwal_kelas_sesi, to both guru and murid — see
        // App\Console\Commands\ProcessJadwalKelasReminders. A reply to
        // this reminder is what closes the "WA confirms, Excel doesn't
        // update" gap (see WaIncomingMessageWebhookController's
        // jadwal-confirmation check).
        $schedule->command('jadwal:process-reminders')
            ->dailyAt('18:00')
            ->withoutOverlapping();
    })
     ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperadminMiddleware::class,
            'active.package' => \App\Http\Middleware\EnsureActivePackage::class,
            'golang.api-key' => \App\Http\Middleware\VerifyGolangApiKey::class,
            'menu.access' => \App\Http\Middleware\EnsureMenuAccess::class,
            'wa.api-key' => \App\Http\Middleware\VerifyWaApiKey::class,
        ]);

        // Stops the browser Back/Forward button from repainting a cached
        // page (e.g. the checkout/payment screens) after logout instead of
        // hitting the server, where 'auth' would otherwise catch it and
        // redirect to /login. See App\Http\Middleware\
        // PreventBackHistoryCache's docblock for the full explanation.
        // Appended to the whole 'web' group (not just 'auth' routes) so
        // any route added later is covered without needing to remember to
        // tag it.
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistoryCache::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Confirmed via curl against production (2026-08-05): the
        // redirect-to-/login response a logged-out session gets bounced
        // to was going out with the WRONG cache header —
        // "Cache-Control: no-cache, private" (Symfony's default) instead
        // of the no-store header App\Http\Middleware\
        // PreventBackHistoryCache sets everywhere else. Root cause:
        // AuthenticationException is thrown by 'auth' middleware, which
        // sits INSIDE the 'web' group's pipeline relative to
        // PreventBackHistoryCache (appended to that same group). A
        // thrown exception unwinds past `$response = $next($request);`
        // in every enclosing middleware as a PHP exception, not a normal
        // return — so PreventBackHistoryCache's header-setting code
        // after that line never runs for this specific response. The
        // exception is instead caught by the framework here, entirely
        // outside the middleware pipeline's normal return path, which is
        // why only this one response class needs its own explicit
        // handling rather than being fixable inside the middleware
        // itself.
        //
        // This was the actual gap behind "logout, press Back, still see
        // the dashboard": PreventBackHistoryCache DOES correctly mark
        // the original authenticated page no-store (so the browser
        // shouldn't repaint it from cache and is forced to re-request),
        // but that fresh re-request's own response — this redirect —
        // was cacheable, so a SECOND Back-press (or the browser's own
        // heuristics) could still resurrect a stale screen instead of
        // reliably landing back on /login every time.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $response = $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 401)
                : redirect()->guest(route('login'));

            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        });

        // Same problem as AuthenticationException above, different
        // trigger: session/CSRF token expired (VerifyCsrfToken throws
        // TokenMismatchException) so the user was shown Laravel's raw
        // 419 "Page Expired" page instead of being bounced back to
        // /login like every other "your session is gone" case. Since
        // VerifyCsrfToken also sits inside the 'web' group ahead of
        // PreventBackHistoryCache, the same exception-unwind gap applies
        // here, so the no-store headers are set explicitly rather than
        // relying on that middleware.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            $response = $request->expectsJson()
                ? response()->json(['message' => 'Sesi Anda telah berakhir, silakan login kembali.'], 419)
                : redirect()->guest(route('login'))
                    ->with('status', 'Sesi Anda telah berakhir, silakan login kembali.');

            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        });
    })->create();
