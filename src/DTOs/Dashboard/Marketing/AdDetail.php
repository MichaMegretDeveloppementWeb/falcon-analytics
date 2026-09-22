<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Marketing;

use Falcon\Analytics\Models\Ad;

/**
 * What the detail screen of an ad shows about the ad and its campaign.
 *
 * @internal
 */
final readonly class AdDetail
{
    /**
     * @param  string|null  $campaignPlatform  null when none was given
     * @param  array<int, array{param: string, value: string}>  $conditions  empty when the ad matches no traffic
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $campaignId,
        public string $campaignName,
        public ?string $campaignPlatform,
        public array $conditions,
    ) {}

    /** The ad's campaign is expected read with it. */
    public static function of(Ad $ad): self
    {
        return new self(
            id: $ad->id,
            name: $ad->name,
            campaignId: $ad->campaign_id,
            campaignName: $ad->campaign->name,
            campaignPlatform: filled($ad->campaign->platform) ? $ad->campaign->platform : null,
            conditions: $ad->match_conditions ?? [],
        );
    }
}
