<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\MaintenanceOutcome;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Falcon\Analytics\Support\RetentionSettings;

/**
 * The erasing of rows, in one place, so its two ways in are one way.
 *
 * The scheduler is the normal path. Opening a screen is the other, because a
 * scheduler on shared hosting stops without a word. Both land here, right
 * after `ArchiveClosedDaysAction`, and the command is a thin wrapper that only
 * turns this into sentences. The profiles left without a session are
 * `PruneProfilesLeftEmptyAction`'s, which takes their lock.
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
        private DailyDetailArchiver $details,
        private EventWriteRepository $events,
        private SessionWriteRepository $sessions,
        private VisitorWriteRepository $visitors,
        private FunnelRegistry $funnels,
    ) {}

    /**
     * Erase what each retention allows · the anonymous page views and clicks,
     * the named events, the sessions · a day only once its summary is written.
     *
     * Everything it needs to decide is read here, and every one of those reads
     * is the caller's to guard · a database that answers none of them is a
     * failure to report, not an exception to let out of a page that has already
     * been served.
     */
    public function prune(): MaintenanceOutcome
    {
        $refusal = RetentionSettings::refusal();

        if ($refusal !== null) {
            return MaintenanceOutcome::refused($refusal);
        }

        $pages = $this->prunePages();
        $rows = array_values(array_filter([$this->pruneNamedEvents(), $this->pruneSessions()]));

        return $rows === [] ? $pages : MaintenanceOutcome::together($pages, ...$rows);
    }

    /** The anonymous page views and clicks past `retention_days`. */
    private function prunePages(): MaintenanceOutcome
    {
        $days = RetentionSettings::pages();

        if ($days === null) {
            return MaintenanceOutcome::nothingToDo('Aucune conservation réglée : rien n’est jamais effacé.');
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

    /** The named events past `event_retention_days`, null when they leave with their sessions. */
    private function pruneNamedEvents(): ?MaintenanceOutcome
    {
        $days = RetentionSettings::namedEvents();

        if ($days === null) {
            return null;
        }

        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();

        if (! Event::query()->where('occurred_at', '<', $cutoff)->where('name', '<>', '')->exists()) {
            return MaintenanceOutcome::nothingToDo("Aucun événement nommé de plus de {$days} jours.");
        }

        $waiting = $this->waitingFor($cutoff, 'aucun événement nommé n’a été effacé');

        if ($waiting !== null) {
            return $waiting;
        }

        // Marked first: a screen read while the rows go reads these days from their totals.
        $this->details->markEventsErasedBefore($cutoff);
        $deleted = $this->events->pruneNamedOlderThan($cutoff);

        return MaintenanceOutcome::erased($deleted, "{$deleted} événement(s) nommé(s) de plus de {$days} jours effacé(s).");
    }

    /**
     * The sessions past `session_retention_days`, their events with them, null
     * when they are kept · the profiles they were on counted again.
     */
    private function pruneSessions(): ?MaintenanceOutcome
    {
        $days = RetentionSettings::sessions();

        if ($days === null) {
            return null;
        }

        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();

        if (! Session::query()->where('last_activity_at', '<', $cutoff)->exists()) {
            return MaintenanceOutcome::nothingToDo("Aucune session de plus de {$days} jours.");
        }

        $waiting = $this->waitingFor($cutoff, 'aucune session n’a été effacée');

        if ($waiting !== null) {
            return $waiting;
        }

        // Marked first: a screen read while the rows go reads these days from their totals.
        $this->details->markSessionsErasedBefore($cutoff);
        ['deleted' => $deleted, 'visitors' => $touched] = $this->sessions->pruneLastActiveBefore($cutoff);
        $this->visitors->recountSessions($touched);

        return MaintenanceOutcome::erased($deleted, "{$deleted} session(s) de plus de {$days} jours effacée(s), avec leurs événements.");
    }

    /** Waiting when the newest day at stake is not summarised yet · the summary only moves forward. */
    private function waitingFor(CarbonImmutable $cutoff, string $nothingErased): ?MaintenanceOutcome
    {
        $lastAtStake = $cutoff->subDay();

        if ($this->details->isArchived($lastAtStake)) {
            return null;
        }

        return MaintenanceOutcome::waiting(sprintf(
            'Le %s n’est pas encore résumé : %s. Lancez analytics:archive d’abord.',
            $lastAtStake->toDateString(),
            $nothingErased,
        ));
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
