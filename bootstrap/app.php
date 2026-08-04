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
    })
     ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperadminMiddleware::class,
            'active.package' => \App\Http\Middleware\EnsureActivePackage::class,
            'golang.api-key' => \App\Http\Middleware\VerifyGolangApiKey::class,
            'menu.access' => \App\Http\Middleware\EnsureMenuAccess::class,
            'wa.api-key' => \App\Http\Middleware\VerifyWaApiKey::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
