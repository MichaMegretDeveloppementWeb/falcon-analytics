<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\DTOs\MaintenanceOutcome;
use Falcon\Analytics\Services\Maintenance;

/**
 * The whole erasing of a run · the rows the retentions allow, then the
 * profiles their sessions left empty. What the command and the catch-up on a
 * screen load both run.
 *
 * @internal
 */
final readonly class PruneAction
{
    public function __construct(
        private Maintenance $maintenance,
        private PruneProfilesLeftEmptyAction $profiles,
    ) {}

    public function execute(): MaintenanceOutcome
    {
        $outcome = $this->maintenance->prune();

        if ($outcome->refused) {
            return $outcome;
        }

        $profiles = $this->profiles->execute();

        return $profiles === 0
            ? $outcome
            : MaintenanceOutcome::together($outcome, MaintenanceOutcome::erased($profiles, "{$profiles} profil(s) resté(s) sans session effacé(s)."));
    }
}
