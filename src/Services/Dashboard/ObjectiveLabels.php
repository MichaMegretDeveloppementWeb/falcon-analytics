<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Marketing\ObjectiveTag;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\AdObjective;

/**
 * Names the objectives of an ad · by their declared funnel or event, or by
 * their reference once it is no longer declared.
 *
 * @internal
 */
final readonly class ObjectiveLabels
{
    public function __construct(
        private FunnelRegistry $funnels,
        private EventRegistry $events,
    ) {}

    /**
     * @param  iterable<AdObjective>  $objectives
     * @return list<ObjectiveTag>
     */
    public function tagsOf(iterable $objectives): array
    {
        $tags = [];
        foreach ($objectives as $objective) {
            $tags[] = new ObjectiveTag($objective->type, $objective->reference, $this->labelOf($objective));
        }

        return $tags;
    }

    private function labelOf(AdObjective $objective): string
    {
        $declared = match ($objective->type) {
            ObjectiveType::Funnel => $this->funnels->get($objective->reference)?->label,
            ObjectiveType::Event => $this->events->get($objective->reference)?->label,
        };

        return $declared ?? $objective->reference;
    }
}
