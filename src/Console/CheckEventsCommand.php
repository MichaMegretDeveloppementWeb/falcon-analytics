<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Illuminate\Console\Command;

/**
 * Verifies that every event referenced by a funnel step is declared in the events
 * file, so funnels never point at an undeclared (and thus unnameable) event.
 */
final class CheckEventsCommand extends Command
{
    protected $signature = 'analytics:events:check';

    protected $description = 'Verify that every funnel step event is a declared tracked event.';

    public function handle(FunnelRegistry $funnels, EventRegistry $events): int
    {
        $declared = $events->names();
        $undeclared = [];

        foreach ($funnels->all() as $funnel) {
            foreach ($funnel->steps() as $step) {
                // eventNames() covers branches too, so a step declared with anyOf is
                // checked branch by branch rather than skipped.
                foreach ($step->eventNames() as $event) {
                    if (! in_array($event, $declared, true)) {
                        $undeclared[] = [$funnel->key, $event];
                    }
                }
            }
        }

        if ($undeclared === []) {
            $this->components->info('Every funnel step event is declared.');

            return self::SUCCESS;
        }

        foreach ($undeclared as [$funnelKey, $event]) {
            $this->components->warn("Funnel [{$funnelKey}] references undeclared event [{$event}].");
        }

        $this->components->info('Declare these events in the events file (or via analytics:events:scan --fix).');

        return self::FAILURE;
    }
}
