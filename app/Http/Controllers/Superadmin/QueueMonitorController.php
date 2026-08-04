<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only-ish dashboard over the `database` queue driver's own tables
 * (`jobs` = still waiting/being worked, `failed_jobs` = gave up after
 * exhausting retries) — this app's QUEUE_CONNECTION is `database` (see
 * .env), not Redis, so Horizon isn't an option here; this is the
 * lightweight equivalent for that driver.
 *
 * Deliberately doesn't touch running workers (see supervisord config
 * managing `teleios-worker`) — it only reads/retries/discards rows in
 * these two tables. The two write actions (retryFailed/destroyFailed)
 * shell out to the actual `queue:retry` / `queue:forget` artisan
 * commands rather than reimplementing their payload handling, so this
 * stays correct even if Laravel changes how a failed job is re-queued
 * internally.
 */
class QueueMonitorController extends Controller
{
    public function index(): View
    {
        $pending = DB::table('jobs')
            ->orderBy('created_at')
            ->paginate(20, ['*'], 'pending_page')
            ->withQueryString();

        $pending->getCollection()->transform(function ($job) {
            $job->job_label = $this->resolveJobLabel($job->payload);
            $job->queued_at = $this->fromUnixTimestamp($job->created_at);
            $job->is_reserved = ! is_null($job->reserved_at);
            // A job sitting unreserved for more than 5 minutes almost
            // always means no worker is currently picking it up (worker
            // down, or stuck on a slow/hanging previous job) — flagged in
            // the view as the "needs attention" case among pending jobs,
            // since a fresh unreserved job is completely normal.
            $job->is_stale = ! $job->is_reserved && $job->queued_at->diffInMinutes(now()) >= 5;

            return $job;
        });

        $failed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(20, ['*'], 'failed_page')
            ->withQueryString();

        $failed->getCollection()->transform(function ($job) {
            $job->job_label = $this->resolveJobLabel($job->payload);
            $job->exception_summary = $this->firstExceptionLine($job->exception);

            return $job;
        });

        $failedCount = DB::table('failed_jobs')->count();
        $pendingCount = DB::table('jobs')->count();
        $oldestPending = DB::table('jobs')->orderBy('created_at')->value('created_at');

        return view('superadmin.queue-monitor.index', [
            'pending' => $pending,
            'failed' => $failed,
            'pendingCount' => $pendingCount,
            'failedCount' => $failedCount,
            'oldestPendingAt' => $oldestPending ? $this->fromUnixTimestamp($oldestPending) : null,
        ]);
    }

    public function retryFailed(string $id): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('success', 'Job dikembalikan ke antrian untuk dicoba ulang.');
    }

    public function retryAllFailed(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'Semua failed job dikembalikan ke antrian.');
    }

    public function destroyFailed(string $id): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $id]);

        return back()->with('success', 'Failed job dihapus.');
    }

    /**
     * Best-effort human-readable job name from a queue payload's JSON.
     * Most of this app's jobs (App\Jobs\SendScheduledWaMessage,
     * App\Jobs\SendAutoReplyMessage) dispatch directly, so `displayName`
     * is already their real class name. Queued Notifications (e.g.
     * App\Notifications\PackageExpiringNotification, sent via
     * ->notify()) get wrapped by Laravel in
     * Illuminate\Notifications\SendQueuedNotifications first, so
     * `displayName` alone would just say "SendQueuedNotifications" for
     * every single one of them — the regex below reaches into that
     * wrapper's serialized `notification` property to pull out the
     * actual notification class instead. Falls back to the wrapper name
     * if that inner class can't be found (e.g. future Laravel versions
     * changing the property's serialized layout) — this is purely
     * cosmetic, so failing safe to something less specific beats
     * crashing this page.
     */
    private function resolveJobLabel(string $payloadJson): string
    {
        $payload = json_decode($payloadJson, true);
        $displayName = $payload['displayName'] ?? 'Unknown job';

        if ($displayName === SendQueuedNotifications::class) {
            $commandString = $payload['data']['command'] ?? '';

            if (preg_match('/\x00\*\x00notification";O:\d+:"([^"]+)"/', $commandString, $matches)) {
                return class_basename($matches[1]) . ' (notification)';
            }
        }

        return class_basename($displayName);
    }

    private function firstExceptionLine(string $exception): string
    {
        $firstLine = strtok($exception, "\n") ?: $exception;

        return Str::limit($firstLine, 160);
    }

    private function fromUnixTimestamp(int $timestamp): Carbon
    {
        return Carbon::createFromTimestamp($timestamp);
    }
}
