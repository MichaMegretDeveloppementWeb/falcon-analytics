<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Middleware;

use Closure;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\Services\Maintenance;
use Falcon\Analytics\Support\AfterTheResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The maintenance's second way in · an administrator opening a screen.
 *
 * **Schedulers on shared hosting stop without a word.** When that happens the
 * summarising stops with them, so the erasing stops too and nothing is lost,
 * but the backlog grows and the summaries fall behind the depth the screens
 * can read.
 *
 * So opening any analytics screen catches the backlog up a little.
 *
 * **How often it runs and how much it takes on are package settings**, in the
 * internal settings file that nothing publishes.
 *
 * **It does the same thing the scheduler does**, through the same action and
 * service, so there is a single set of guards to keep in step.
 *
 * Four things keep it from being felt ·
 *
 * - it acts in `terminate()`, so the page has already left ;
 * - a marker holds it to once per interval ;
 * - a lock keeps two administrators from running it at once ;
 * - it is bounded to a few days a run, so a long backlog is caught up over
 *   several visits rather than in one.
 *
 * @internal the host never places this · the package puts it on its own screens
 */
final class CatchesUpTheMaintenance
{
    private const MARKER = 'falcon-analytics:maintenance:last-run';

    private const LOCK = 'falcon-analytics:maintenance:running';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->catchesUp()) {
            AfterTheResponse::keepRunning();
        }

        return $next($request);
    }

    /**
     * After the response has gone.
     *
     * Everything here is swallowed and logged · a maintenance that failed must
     * never reach a page that has already been served correctly, and the page
     * is gone anyway.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->catchesUp()) {
            return;
        }

        try {
            $this->catchUp();
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))
                ->warning('Analytics maintenance on screen load failed.', ['exception' => $e]);
        }
    }

    private function catchesUp(): bool
    {
        return config('analytics.internal.maintenance.on_screen_load') === true;
    }

    private function catchUp(): void
    {
        $interval = max(1, (int) config('analytics.internal.maintenance.interval_minutes', 60));

        if (Cache::get(self::MARKER) !== null) {
            return;
        }

        // Held for one run at most, so a process that dies holding it blocks nothing for long.
        Cache::lock(self::LOCK, 300)->get(function () use ($interval): void {
            // Written before the work, not after: a run that fails halfway must
            // not have the next page load start it all over again.
            Cache::put(self::MARKER, true, $interval * 60);

            $days = max(1, (int) config('analytics.internal.maintenance.days_per_run', 7));

            // Not through the commands: booting the console kernel in terminate() breaks the session store.
            app(ArchiveClosedDaysAction::class)->execute($days);
            app(Maintenance::class)->prune();
        });
    }
}
