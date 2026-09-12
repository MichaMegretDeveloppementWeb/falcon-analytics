<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * The names of the ramp a chart slices, and never its values.
 *
 * **Why names and not colours.** The values live in `resources/css/theme.css`,
 * in both themes, so a host redefines one without a build. CSS cannot be
 * sliced by PHP and PHP cannot read a custom property, so what travels between
 * them is the name: a view slices this list, a swatch writes `var(…)` around
 * it, and a chart hands it to `window.falconToken()` because a canvas resolves
 * nothing on its own.
 *
 * **Why one place.** Four views used to declare the ramp as literal colours,
 * and three of them had drifted: two steps differed between screens that were
 * meant to look alike. Nobody decided that — a copy aged, and nothing could
 * say so.
 */
final class ChartPalette
{
    /**
     * From the most prominent to the palest.
     *
     * A chart takes the first n for its n segments, so the order is the whole
     * contract: step one is what the eye lands on.
     *
     * @var list<string>
     */
    public const SERIES = [
        '--an-series-1',
        '--an-series-2',
        '--an-series-3',
        '--an-series-4',
        '--an-series-5',
        '--an-series-6',
    ];

    /**
     * The realtime board's ramp, and it is a second one on purpose.
     *
     * That screen is built to be read from across a room, so it opens on the
     * accent and the live green rather than on six shades of one blue. Its two
     * first tokens derive from those, so a host retinting the accent gets a
     * board that still agrees with itself.
     *
     * @var list<string>
     */
    public const LIVE = [
        '--an-live-1',
        '--an-live-2',
        '--an-live-3',
        '--an-live-4',
        '--an-live-5',
        '--an-live-6',
    ];

    /**
     * The first `$count` steps of a ramp, and its last one repeated if more are
     * asked for than it holds.
     *
     * Repeating beats wrapping around: two adjacent slices in the same colour
     * read as one slice, which is wrong but honest, where a wrap makes the
     * seventh segment look as important as the first.
     *
     * @param  list<string>  $ramp
     * @return list<string>
     */
    public static function steps(int $count, array $ramp = self::SERIES): array
    {
        if ($count <= 0 || $ramp === []) {
            return [];
        }

        return array_pad(array_slice($ramp, 0, $count), $count, $ramp[count($ramp) - 1]);
    }
}
