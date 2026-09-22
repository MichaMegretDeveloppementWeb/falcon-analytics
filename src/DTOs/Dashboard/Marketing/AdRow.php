<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Marketing;

use Falcon\Analytics\Models\Ad;

/**
 * A line of a list of ads · all of them, or a campaign's.
 *
 * @internal
 */
final readonly class AdRow
{
    /**
     * @param  array<int, array{param: string, value: string}>  $conditions
     * @param  list<ObjectiveTag>  $objectives
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $campaignId,
        public string $campaignName,
        public array $conditions,
        public array $objectives,
    ) {}

    /**
     * @param  list<ObjectiveTag>  $objectives  the ad's objectives, named
     */
    public static function of(Ad $ad, string $campaignName, array $objectives): self
    {
        return new self(
            id: $ad->id,
            name: $ad->name,
            campaignId: $ad->campaign_id,
            campaignName: $campaignName,
            conditions: $ad->match_conditions ?? [],
            objectives: $objectives,
        );
    }
}
