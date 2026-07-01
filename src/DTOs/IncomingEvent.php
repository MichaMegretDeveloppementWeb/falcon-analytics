<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;

final readonly class IncomingEvent
{
    /**
     * @param  array<string, mixed>|null  $props
     */
    public function __construct(
        public EventType $type,
        public CarbonImmutable $occurredAt,
        public ?string $name = null,
        public ?string $route = null,
        public ?string $url = null,
        public ?string $targetSelector = null,
        public ?string $targetText = null,
        public ?array $props = null,
        public ?float $value = null,
    ) {}
}
