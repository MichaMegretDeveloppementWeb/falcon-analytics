<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Services\DailyCountArchiver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Erases the anonymous page views and clicks past the retention window.
 *
 * **It never erases anything a screen still reads.** What goes has already been
 * counted into the daily summaries; what stays is everything carrying a name —
 * the events screen, the funnels and the marketing conversions live on those —
 * plus the page views on routes a declared funnel steps through.
 *
 * **And it refuses a day the archiving has not treated.** That single rule is
 * what makes a dead scheduler harmless rather than lossy: no summary, no
 * erasing, and the backlog waits.
 *
 * @internal
 */
final class PruneCommand extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Efface les pages vues et clics anonymes au-delà de la conservation, une fois le jour résumé.';

    public function handle(
        EventWriteRepository $events,
        DailyCountArchiver $archiver,
        FunnelRegistry $funnels,
    ): int {
        $days = config('analytics.retention_days');

        if ($days === null) {
            $this->components->info('Aucune conservation réglée : rien n’est jamais effacé.');

            return self::SUCCESS;
        }

        if (! is_int($days) || $days < 1) {
            $this->components->error(
                'analytics.retention_days doit être un nombre de jours d’au moins 1, ou null pour ne jamais effacer. '
                ."Valeur lue : {$this->describe($days)}."
            );

            return self::FAILURE;
        }

        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();

        /*
         * The last day that would be erased. Erasing runs up to the cutoff, so
         * the day before it is the newest one at stake; if that one has not
         * been summarised, neither have the older ones — the archiving only
         * ever moves forward.
         */
        $lastAtStake = $cutoff->subDay();

        /*
         * Every read is inside the guard, and that is deliberate: a database
         * that answers none of them is a failure to report, not an exception to
         * let out. The two questions before the erasing are reads like any
         * other, and leaving them outside would turn a missing table into a
         * stack trace where the rest of the command gives a sentence.
         */
        try {
            /*
             * Nothing that old exists, so there is nothing to erase and nothing
             * to complain about. Asked first because a site younger than its
             * own retention would otherwise be told every night that a day it
             * never had has not been summarised.
             */
            if (! Event::query()->where('occurred_at', '<', $cutoff)->exists()) {
                $this->components->info("Rien de plus vieux que {$days} jours : rien à effacer.");

                return self::SUCCESS;
            }

            if (! $archiver->isArchived($lastAtStake)) {
                $this->components->warn(sprintf(
                    'Le %s n’est pas encore résumé : rien n’a été effacé. Lancez analytics:archive d’abord.',
                    $lastAtStake->toDateString(),
                ));

                return self::SUCCESS;
            }

            $deleted = $events->pruneAnonymousOlderThan($cutoff, $this->routesFunnelsNeed($funnels));
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics prune failed.', ['exception' => $e]);
            $this->components->error('L’effacement a échoué ; voyez le canal de journal de l’analytique.');

            return self::FAILURE;
        }

        $this->components->info(
            "{$deleted} page(s) vue(s) et clic(s) anonymes de plus de {$days} jours effacé(s). "
            .'Les événements nommés sont gardés.'
        );

        return self::SUCCESS;
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
    private function routesFunnelsNeed(FunnelRegistry $funnels): array
    {
        $routes = [];

        foreach ($funnels->all() as $funnel) {
            foreach ($funnel->steps() as $step) {
                $routes = [...$routes, ...$step->routeNames()];
            }
        }

        return array_values(array_unique($routes));
    }

    private function describe(mixed $value): string
    {
        return is_scalar($value) ? var_export($value, true) : get_debug_type($value);
    }
}
