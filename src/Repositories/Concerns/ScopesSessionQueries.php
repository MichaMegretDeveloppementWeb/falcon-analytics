<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Concerns;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Support\DatabaseEngine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query primitives for the dashboard read models: the non-bot session
 * scope and the three bits of date/duration SQL Eloquent cannot express, so
 * each finality-specific repository builds on the same trusted base.
 *
 * **The SQL below is MySQL's, and only MySQL's.** The package supports MySQL
 * and MariaDB, which write these three expressions identically — `DATE`,
 * `DATE_FORMAT` and `TIMESTAMPDIFF` exist in both under the same names, so the
 * second costs not one line here. {@see DatabaseEngine} holds that list, and
 * refuses the install on anything else.
 *
 * It used to branch on the driver, with arms for PostgreSQL, SQL Server and
 * SQLite. No test ever ran on any of them — which is exactly what made the
 * branches worth removing: the code promised four engines, the notice promised
 * three, and the suite proved one.
 *
 * @internal
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
     * SQL truncating a timestamp to a 'YYYY-MM-DD' string, so daily buckets
     * group on the day rather than on the instant. The column is a trusted
     * internal constant, never user input.
     *
     * `literal-string` holds that last sentence: a column coming from a request
     * stops compiling rather than reaching `selectRaw()`.
     *
     * A method rather than a constant, for the ten call sites that name it: the
     * name says what the SQL means, which the SQL itself does not.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    private function dayExpression(string $column): string
    {
        return "DATE({$column})";
    }

    /**
     * The same, to the minute, for the realtime screen — see
     * {@see dayExpression()}.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    private function minuteExpression(string $column): string
    {
        return "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i')";
    }

    /**
     * The difference in seconds between two timestamp columns, both trusted
     * internal constants — see {@see dayExpression()}.
     *
     * @param  literal-string  $start
     * @param  literal-string  $end
     * @return literal-string
     */
    private function durationSecondsExpression(string $start, string $end): string
    {
        return "TIMESTAMPDIFF(SECOND, {$start}, {$end})";
    }
}
