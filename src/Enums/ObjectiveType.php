<?php

declare(strict_types=1);

namespace Falcon\Analytics\Enums;

/**
 * The kind of conversion objective assigned to an ad.
 */
enum ObjectiveType: string
{
    case Funnel = 'funnel';
    case Event = 'event';
}
