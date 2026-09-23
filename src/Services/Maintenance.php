<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\MaintenanceOutcome;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\EventWriteRepository;

/**
 * The erasing, in one place, so its two ways in are one way.
 *
 * The scheduler is the normal path. Opening a screen is the other, because a
 * scheduler on shared hosting stops without a word. Both land here, right
 * after `ArchiveClosedDaysAction`, and the command is a thin wrapper that only
 * turns this into sentences.
 *
 * Neither way goes through the console: booting the console kernel inside a
 * web request's `terminate()` disturbs the session store, whose handler loses
 * the request it was given, and the session save that follows fails.
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

        // Asked first: a site younger than its retention has nothing to erase and nothing to warn about.
        if (! Event::query()->where('occurred_at', '<', $cutoff)->exists()) {
            return MaintenanceOutcome::nothingToDo("Rien de plus vieux que {$days} jours : rien à effacer.");
        }

        // The archiving only moves forward: if the newest day at stake is summarised, so are the older ones.
        $lastAtStake = $cutoff->subDay();

        if (! $this->archiver->isArchived($lastAtStake)) {
            return MaintenanceOutcome::waiting(sprintf(
                'Le %s n’est pas encore résumé : rien n’a été effacé. Lancez analytics:archive d’abord.',
                $lastAtStake->toDateString(),
            ));
        }

        // Every day erased is already read from its summary, so a batch run stopped halfway costs no figure.
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
     * Read at each run, not stored · a funnel declared today protects its
     * routes from the next erasing, and one removed stops protecting them.
     * Neither reaches back over what is already gone.
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
