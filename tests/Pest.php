<?php

declare(strict_types=1);

use Falcon\Analytics\Tests\TestCase;

// Feature tests boot the Testbench app; Unit tests are pure (no Laravel boot).
pest()->extend(TestCase::class)->in('Feature');
pest()->extend(PHPUnit\Framework\TestCase::class)->in('Unit');
