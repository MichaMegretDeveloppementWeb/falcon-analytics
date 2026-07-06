<?php

use Falcon\Analytics\Events\TrackedEvent;

TrackedEvent::define('sample.action', 'Sample action', value: 5.0);
TrackedEvent::define('sample.other', 'Sample other');
