<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Services\Maintenance;
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
 * The deciding lives in `Maintenance`, which the catch-up on a screen load runs
 * as well · one decision, two ways of reporting it.
 *
 * @internal
 */
final class PruneCommand extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Efface les pages vues et clics anonymes au-delà de la conservation, une fois le jour résumé.';

    public function handle(Maintenance $maintenance): int
    {
        try {
            $outcome = $maintenance->prune();
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics prune failed.', ['exception' => $e]);
            $this->components->error('L’effacement a échoué ; voyez le canal de journal de l’analytique.');

            return self::FAILURE;
        }

        if ($outcome->refused) {
            $this->components->error($outcome->said);

            return self::FAILURE;
        }

        if ($outcome->erased === 0) {
            $this->components->warn($outcome->said);

            return self::SUCCESS;
        }

        $this->components->info($outcome->said);

        return self::SUCCESS;
    }
}
