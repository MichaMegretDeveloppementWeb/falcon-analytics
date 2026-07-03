<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Concerns;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query primitives for the dashboard read models: the non-bot session
 * scope and the driver-aware date/duration SQL, so each finality-specific
 * repository builds on the same trusted base.
 */
trait ScopesSessionQueries
{
    /**
     * Base query: non-bot sessions within the period, narrowed to a subject type.
     *
     * @return Builder<Session>
     */
    private function sessionScope(Period $period, ?string $subjectType): Builder
    {
        return Session::query()
            ->where('is_bot', false)
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->where('subject_type', $subjectType));
    }

    /**
     * The active database driver name (mysql / sqlite / pgsql / sqlsrv).
     */
    private function driver(): string
    {
        /** @var Connection $connection */
        $connection = Session::query()->getConnection();

        return $connection->getDriverName();
    }

    /**
     * Driver-aware SQL truncating a timestamp to a 'YYYY-MM-DD' string so daily
     * buckets group identically on every database. The column is a trusted
     * internal constant, never user input.
     */
    private function dayExpression(string $column): string
    {
        return match ($this->driver()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'sqlsrv' => "CONVERT(varchar(10), {$column}, 23)",
            default => "DATE({$column})",
        };
    }

    /**
     * Driver-aware SQL for the difference in seconds between two timestamp
     * columns (both trusted internal constants).
     */
    private function durationSecondsExpression(string $start, string $end): string
    {
        return match ($this->driver()) {
            'sqlite' => "(strftime('%s', {$end}) - strftime('%s', {$start}))",
            'pgsql' => "EXTRACT(EPOCH FROM ({$end} - {$start}))",
            'sqlsrv' => "DATEDIFF(SECOND, {$start}, {$end})",
            default => "TIMESTAMPDIFF(SECOND, {$start}, {$end})",
        };
    }
}
