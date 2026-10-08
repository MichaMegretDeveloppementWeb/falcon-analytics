<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * What a screen says in place of a figure that counts distinct people, over
 * days whose sessions or named events are erased.
 *
 * @internal
 */
final class RetentionLabel
{
    /** In place of the figure · « Indisponible au-delà de 90 jours de conservation ». */
    public static function unavailable(?int $days): string
    {
        return $days === null
            ? __('Indisponible au-delà de la conservation')
            : __('Indisponible au-delà de :count jours de conservation', ['count' => NumberLabel::for($days)]);
    }

    /** In place of the comparison with the previous period. */
    public static function noComparison(): string
    {
        return __('Comparaison indisponible');
    }
}
