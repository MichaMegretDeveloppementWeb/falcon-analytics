<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums;

enum EventType: string
{
    case Pageview = 'pageview';
    case Click = 'click';
    case Custom = 'custom';
    case Heartbeat = 'heartbeat';
}
