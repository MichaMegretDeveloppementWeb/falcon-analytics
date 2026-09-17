<?php

declare(strict_types=1);

use Falcon\Analytics\Funnels\Funnel;

Funnel::define('before', 'Before the failure')
    ->step('Viewed', value: 1, route: 'home');

throw new RuntimeException('Malformed funnels file.');
