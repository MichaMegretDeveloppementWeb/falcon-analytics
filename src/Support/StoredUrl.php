<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use InvalidArgumentException;

/**
 * What counts as « the same page » when stored addresses are grouped.
 *
 * **The address is stored whole, query string included**, and that is right —
 * a session's journey shows the page a visitor actually opened. But « les pages
 * les plus vues » asks a different question, and asking it of the whole address
 * answers badly.
 *
 * **Measured 2026-09-14** · one page opened three times, twice through a
 * campaign link, came back as three rows of one view each — and the screen
 * displayed the same path on all three, since it renders the path and grouped
 * on the address. On any site receiving campaign traffic the effect is not
 * subtle: `fbclid` is unique per click, so the real top page is split into as
 * many rows as it had visits and never reaches the top of the list.
 *
 * Grouping on the address up to the `?` is what every tool of this kind does,
 * and it is what the screen was already showing.
 *
 * @internal
 */
final class StoredUrl
{
    /**
     * Driver-aware SQL for the address without its query string.
     *
     * The column is a trusted internal constant, never user input · the
     * `literal-string` type holds that sentence, so a column coming from a
     * request stops compiling rather than reaching `selectRaw()`. Same
     * discipline as the day expression this sits beside.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function pathExpression(string $driver, string $column): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => "SUBSTRING_INDEX({$column}, '?', 1)",
            'pgsql' => "split_part({$column}, '?', 1)",
            'sqlite' => "CASE WHEN instr({$column}, '?') > 0 "
                ."THEN substr({$column}, 1, instr({$column}, '?') - 1) ELSE {$column} END",
            'sqlsrv' => "CASE WHEN CHARINDEX('?', {$column}) > 0 "
                ."THEN LEFT({$column}, CHARINDEX('?', {$column}) - 1) ELSE {$column} END",

            // Raising rather than falling back on the whole address: a silent
            // fallback would put a driver's figures quietly at odds with every
            // other driver's, and with the summaries built beside them.
            default => throw new InvalidArgumentException(
                "Falcon Analytics ne sait pas isoler le chemin d'une adresse sur le moteur [{$driver}]."
            ),
        };
    }
}
