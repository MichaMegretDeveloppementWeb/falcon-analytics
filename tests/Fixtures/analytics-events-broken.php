<?php

declare(strict_types=1);

use Falcon\Analytics\Events\TrackedEvent;

TrackedEvent::define('sample.before', 'Before the failure', value: 5);

throw new RuntimeException('Malformed events file.');
