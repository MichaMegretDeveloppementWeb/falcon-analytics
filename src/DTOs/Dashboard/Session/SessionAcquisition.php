<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

/**
 * How a session arrived · its channel, the page it landed on, and what the
 * address and the referrer said about it.
 *
 * @internal
 */
final readonly class SessionAcquisition
{
    /**
     * @param  array<string, string>  $campaignParameters  label => value, the ones the address carried
     */
    public function __construct(
        public ?string $source,
        public string $icon,
        public string $description,
        public ?string $searchQuery,
        public ?string $campaignTerm,
        public ?string $landingRoute,
        public ?string $landingUrl,
        public ?string $referrer,
        public array $campaignParameters,
    ) {}
}
