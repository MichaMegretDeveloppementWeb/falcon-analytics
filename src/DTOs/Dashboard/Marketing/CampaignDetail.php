<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Marketing;

use Falcon\Analytics\Models\Campaign;

/**
 * What the detail screen of a campaign shows about the campaign itself · its
 * ads are a list, and come apart.
 *
 * @internal
 */
final readonly class CampaignDetail
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
    ) {}

    public static function of(Campaign $campaign): self
    {
        return new self(
            id: $campaign->id,
            name: $campaign->name,
            platform: filled($campaign->platform) ? $campaign->platform : null,
            conditions: $campaign->match_conditions ?? [],
        );
    }
}
