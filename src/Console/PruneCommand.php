<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Illuminate\Console\Command;

final class PruneCommand extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Delete raw analytics events older than the retention window.';

    public function handle(EventWriteRepository $events): int
    {
        $days = (int) config('analytics.retention_days');

        if ($days <= 0) {
            $this->components->warn('Retention is disabled (analytics.retention_days <= 0); nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = $events->pruneOlderThan(CarbonImmutable::now()->subDays($days));

        $this->components->info("Pruned {$deleted} event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
