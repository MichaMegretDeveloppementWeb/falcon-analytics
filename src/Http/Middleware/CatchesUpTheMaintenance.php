<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Middleware;

use Closure;
use Falcon\Analytics\Services\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The maintenance's second way in · an administrator opening a screen.
 *
 * **Schedulers on shared hosting stop without a word.** When that happens the
 * summarising stops with them, so the erasing stops too — nothing is lost, by
 * design — but the backlog grows and the summaries fall behind the depth the
 * screens can read. Someone has to notice, and nobody does.
 *
 * So opening any analytics screen catches the backlog up a little. Matomo has
 * done this for years.
 *
 * **What it does is settled here, not by the host.** How often it may run and
 * how much it takes on are design decisions, so they live in the package's own
 * settings file — the one nothing publishes. Two reasonable hosts would not
 * answer these differently, and a value that suits nobody is a defect to fix
 * rather than a question to ask of every project.
 *
 * **It does the same thing the scheduler does**, through the same service, and
 * that is deliberate rather than convenient: a second path with its own logic
 * would be a second set of guards to keep in step, and the one that runs least
 * often is the one that would rot.
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
        if (config('analytics.internal.maintenance.on_screen_load') !== true) {
            return;
        }

        try {
            $this->catchUp();
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))
                ->warning('Analytics maintenance on screen load failed.', ['exception' => $e]);
        }
    }

    private function catchUp(): void
    {
        $interval = max(1, (int) config('analytics.internal.maintenance.interval_minutes', 60));

        if (Cache::get(self::MARKER) !== null) {
            return;
        }

        /*
         * The lock is taken for the length of one run and released at the end,
         * so a process that dies holding it blocks nothing for long. `get()`
         * with a callback does both, and answers false when someone else has
         * it — in which case this visit simply does nothing.
         */
        Cache::lock(self::LOCK, 300)->get(function () use ($interval): void {
            // Written before the work, not after: a run that fails halfway must
            // not have the next page load start it all over again.
            Cache::put(self::MARKER, true, $interval * 60);

            $days = max(1, (int) config('analytics.internal.maintenance.days_per_run', 7));

            /*
             * The very thing the scheduler's two commands do, in their order,
             * and NOT through those commands · booting the console kernel
             * inside a request's terminate disturbed the session store, whose
             * handler then no longer had the request it was given. Four Search
             * Console essays fell on it. Nothing about analytics, everything
             * about calling a console from a request.
             */
            $maintenance = app(Maintenance::class);
            $maintenance->archive($days);
            $maintenance->prune();
        });
    }
}
