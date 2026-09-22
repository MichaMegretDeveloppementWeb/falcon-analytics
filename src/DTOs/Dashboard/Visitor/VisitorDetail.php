<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Visitor;

use Carbon\CarbonImmutable;

/**
 * Everything the detail screen of a visitor shows about them, prepared for it ·
 * their sessions are a list, and come apart.
 *
 * @internal
 */
final readonly class VisitorDetail
{
    /**
     * @param  string  $kind  the kind of account behind the visitor, or that they are anonymous
     * @param  bool  $isIdentified  the visitor is tied to an account of the host
     * @param  int  $deviceSessions  the sessions the device split counts
     * @param  list<DeviceShare>  $devices  empty when no session counts
     * @param  list<SourceShare>  $sources  empty when no session counts
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public string $kind,
        public bool $isIdentified,
        public bool $isReturning,
        public CarbonImmutable $firstSeenAt,
        public int $sessionCount,
        public int $pageviewCount,
        public string $averageDuration,
        public string $pagesPerSession,
        public int $deviceSessions,
        public array $devices,
        public array $sources,
    ) {}
}
