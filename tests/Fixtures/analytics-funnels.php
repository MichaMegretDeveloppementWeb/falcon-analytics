<?php

declare(strict_types=1);

use Falcon\Analytics\Funnels\Funnel;

Funnel::define('sample', 'Sample funnel')
    ->step('Viewed', value: 1, route: 'home')
    ->step('Acted', value: 5, event: 'sample.action');
