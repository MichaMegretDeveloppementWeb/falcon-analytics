<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\MaintenanceOutcome;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\EventWriteRepository;

/**
 * Summarising and erasing, in one place, so the two ways in are one way.
 *
 * The scheduler is the normal path. Opening a screen is the other, because a
 * scheduler on shared hosting stops without a word. **Both land here**, and the
 * commands are thin wrappers that only turn this into sentences.
 *
 * **It was Artisan::call from the middleware at first, and that was wrong.**
 * Booting the console kernel inside a web request's `terminate()` disturbed the
 * session store: its handler lost the request it had been given, and the
 * session save that follows fails — on the Search Console screens, which use
 * the session heavily. Nothing about analytics, everything about calling a
 * console from a request.
 *
 * @internal
 */
final readonly class Maintenance
{
    public function __construct(
        private DailyCountArchiver $archiver,
        private EventWriteRepository $events,
        private FunnelRegistry $funnels,
    ) {}

    /**
     * Summarise the closed days waiting for it.
     *
     * @param  int|null  $limit  days at most · null takes them all
     * @return list<string> the days summarised, as Y-m-d
     */
    public function archive(?int $limit = null): array
    {
        return $this->archiver->run($limit);
    }

    /**
     * Erase the anonymous page views and clicks past the retention window.
     *
     * Everything it needs to decide is read here, and every one of those reads
     * is the caller's to guard · a database that answers none of them is a
     * failure to report, not an exception to let out of a page that has already
     * been served.
     */
    public function prune(): MaintenanceOutcome
    {
        $days = config('analytics.retention_days');

        if ($days === null) {
            return MaintenanceOutcome::nothingToDo('Aucune conservation réglée : rien n’est jamais effacé.');
        }

        if (! is_int($days) || $days < 1) {
            return MaintenanceOutcome::refused(
                'analytics.retention_days doit être un nombre de jours d’au moins 1, ou null pour ne jamais '
                .'effacer. Valeur lue : '.(is_scalar($days) ? var_export($days, true) : get_debug_type($days)).'.'
            );
        }

        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();

        /*
         * Nothing that old exists, so there is nothing to erase and nothing to
         * complain about. Asked first because a site younger than its own
         * retention would otherwise be told every night that a day it never had
         * has not been summarised.
         */
        if (! Event::query()->where('occurred_at', '<', $cutoff)->exists()) {
            return MaintenanceOutcome::nothingToDo("Rien de plus vieux que {$days} jours : rien à effacer.");
        }

        /*
         * The last day that would be erased. Erasing runs up to the cutoff, so
         * the day before it is the newest one at stake; if that one has not been
         * summarised, neither have the older ones — the archiving only ever
         * moves forward.
         */
        $lastAtStake = $cutoff->subDay();

        if (! $this->archiver->isArchived($lastAtStake)) {
            return MaintenanceOutcome::waiting(sprintf(
                'Le %s n’est pas encore résumé : rien n’a été effacé. Lancez analytics:archive d’abord.',
                $lastAtStake->toDateString(),
            ));
        }

        /*
         * Every day erased here is already read from its summary · the guard
         * above made sure it holds one. So an erasing that stops halfway, being
         * made in batches, costs no figure · the leftovers simply go next time.
         */
        $deleted = $this->events->pruneAnonymousOlderThan($cutoff, $this->routesFunnelsNeed());

        return MaintenanceOutcome::erased(
            $deleted,
            "{$deleted} page(s) vue(s) et clic(s) anonymes de plus de {$days} jours effacé(s). "
            .'Les événements nommés sont gardés.'
        );
    }

    /**
     * The routes a declared funnel steps through, which therefore survive.
     *
     * **Read at each run, not stored** · a funnel declared today protects its
     * routes from tomorrow's erasing, and one removed stops protecting them.
     * Neither can reach back over what is already gone, and that limit belongs
     * to the documentation rather than to a workaround here.
     *
     * @return list<string>
     */
    private function routesFunnelsNeed(): array
    {
        $routes = [];

        foreach ($this->funnels->all() as $funnel) {
            foreach ($funnel->steps() as $step) {
                $routes = [...$routes, ...$step->routeNames()];
            }
        }

        return array_values(array_unique($routes));
    }
}
