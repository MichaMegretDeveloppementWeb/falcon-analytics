<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Fixtures;

use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;

/**
 * Exposes the trait's three SQL expressions, private to the read repositories,
 * to a test.
 *
 * A named class, so static analysis types its methods.
 */
final class ExposedSessionQueries
{
    use ScopesSessionQueries;

    public function day(): string
    {
        return $this->dayExpression('started_at');
    }

    public function minute(): string
    {
        return $this->minuteExpression('occurred_at');
    }

    public function duration(): string
    {
        return $this->durationSecondsExpression('started_at', 'last_activity_at');
    }
}
