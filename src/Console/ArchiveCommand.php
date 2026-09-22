<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Summarises the closed days, so the purge can erase their detail without
 * costing a single figure.
 *
 * **It runs before the purge, always.** The purge refuses a day this has not
 * treated, which is what makes a dead scheduler harmless rather than lossy.
 *
 * @internal
 */
final class ArchiveCommand extends Command
{
    protected $signature = 'analytics:archive
                            {--days= : Nombre maximal de jours traités en un passage}';

    protected $description = 'Résume les jours clos, pour que la purge puisse effacer leur détail sans perte.';

    public function handle(ArchiveClosedDaysAction $archive): int
    {
        $limit = $this->option('days');
        $limit = is_string($limit) && $limit !== '' ? max(1, (int) $limit) : null;

        try {
            $days = $archive->execute($limit);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics archive failed.', ['exception' => $e]);
            $this->components->error('Le résumé a échoué ; voyez le canal de journal de l’analytique.');

            return self::FAILURE;
        }

        if ($days === []) {
            $this->components->info('Rien à résumer : tous les jours clos le sont déjà.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d jour(s) résumé(s), du %s au %s.',
            count($days),
            $days[0],
            $days[count($days) - 1],
        ));

        return self::SUCCESS;
    }
}
