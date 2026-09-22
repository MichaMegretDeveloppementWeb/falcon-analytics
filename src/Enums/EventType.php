<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums;

enum EventType: string
{
    case Pageview = 'pageview';
    case Click = 'click';
    case Custom = 'custom';
    case Heartbeat = 'heartbeat';

    /** Whether an event of this type becomes a row · a heartbeat only keeps its session alive. */
    public function isStored(): bool
    {
        return $this !== self::Heartbeat;
    }
}
