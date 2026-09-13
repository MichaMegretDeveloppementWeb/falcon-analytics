<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** @internal */
final class PruneCommand extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Supprime les événements bruts plus vieux que la fenêtre de rétention.';

    public function handle(EventWriteRepository $events): int
    {
        $days = (int) config('analytics.retention_days');

        if ($days <= 0) {
            $this->components->warn('La rétention est désactivée (analytics.retention_days <= 0) : rien n’a été supprimé.');

            return self::SUCCESS;
        }

        try {
            $deleted = $events->pruneOlderThan(CarbonImmutable::now()->subDays($days));
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics prune failed.', ['exception' => $e]);
            $this->components->error('La suppression a échoué ; voyez le canal de journal de l’analytique.');

            return self::FAILURE;
        }

        $this->components->info("{$deleted} événement(s) de plus de {$days} jours supprimé(s).");

        return self::SUCCESS;
    }
}
