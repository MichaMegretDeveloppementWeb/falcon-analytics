<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The visitors filter · everyone, the subjects of one declared guard, or the
 * visitors no guard named. A value it does not offer reads as everyone, so an
 * address written by hand filters nothing it was not given.
 *
 * @internal
 */
final class SubjectFilter
{
    /** The choice of the visitors no guard named · never a guard's name. */
    public const NOBODY = 'none';

    /**
     * What a chosen value filters on · a guard, NOBODY, or null for everyone.
     * NOBODY is offered only beside a declared guard.
     */
    public static function chosen(string $subject): ?string
    {
        $guards = DeclaredGuards::of('subject_guards');

        if ($subject === self::NOBODY) {
            return $guards === [] ? null : self::NOBODY;
        }

        return in_array($subject, $guards, true) ? $subject : null;
    }

    /**
     * Narrow a read to the chosen visitors, on the column it already filters on.
     *
     * @template TQuery of EloquentBuilder<*>|QueryBuilder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, ?string $chosen, string $column = 'subject_type'): EloquentBuilder|QueryBuilder
    {
        match ($chosen) {
            null => null,
            self::NOBODY => $query->whereNull($column),
            default => $query->where($column, $chosen),
        };

        return $query;
    }
}
