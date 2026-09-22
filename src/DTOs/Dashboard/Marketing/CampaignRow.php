<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Marketing;

use Falcon\Analytics\Models\Campaign;

/**
 * A line of the campaign list, prepared for it.
 *
 * @internal
 */
final readonly class CampaignRow
{
    /**
     * @param  string|null  $platform  null when none was given
     * @param  array<int, array{param: string, value: string}>  $conditions  empty when the campaign matches no traffic
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $platform,
        public array $conditions,
        public int $adsCount,
    ) {}

    /** The campaign as its line shows it · its ads counted by the read. */
    public static function of(Campaign $campaign): self
    {
        return new self(
            id: $campaign->id,
            name: $campaign->name,
            platform: filled($campaign->platform) ? $campaign->platform : null,
            conditions: $campaign->match_conditions ?? [],
            adsCount: (int) $campaign->getAttribute('ads_count'),
        );
    }
}
