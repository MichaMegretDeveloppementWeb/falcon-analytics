<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The six drawings of the package follow a theme switch, and all in one way.
 *
 * A chart is painted on a canvas, which no stylesheet reaches: it keeps the
 * colours it was handed. So somebody has to hand them over again when the theme
 * changes — and until 2026-09-13 that somebody was each component in turn,
 * which produced the same watcher written six times and three behaviours.
 *
 * Measured in a browser, that day, switching to dark on a drawn page: the
 * doughnuts repainted, the area chart and the realtime line kept `#1684ea` and
 * `#10b981` — the light ramp — while the tokens held `#3b8fe8` and `#34d399`,
 * and the sparklines and the map markers kept theirs too. Nobody had decided
 * any of it; copies had aged.
 *
 * **The split is the point of these tests.** The frame — graduations, grid,
 * tooltip, legend — is identical for every chart of the suite and comes from
 * the kit's tokens, so the kit repaints it once for the whole page. What stays
 * here is the only part that differs: the colour of a component's own series.
 *
 * No database here: it reads files, and nothing else.
 */
final class EveryChartFollowsTheThemeTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function drawings(): array
    {
        return [
            ['area-chart'],
            ['live-line'],
            ['sparkline'],
            ['donut'],
            ['live-donut'],
            ['world-map'],
        ];
    }

    #[DataProvider('drawings')]
    public function test_it_listens_for_the_theme_change(string $component): void
    {
        $this->assertStringContainsString(
            'x-on:theme-changed.window=',
            $this->source($component),
            "{$component} does not follow a theme switch: what it drew keeps the colours of the theme before.",
        );
    }

    /**
     * And it asks for its colours again rather than reusing what it resolved.
     *
     * A component that listens and repaints from a value captured at first draw
     * repaints the same thing, which is the failure this guards against.
     */
    #[DataProvider('drawings')]
    public function test_it_asks_for_its_colours_again(string $component): void
    {
        $source = $this->source($component);
        $handler = $this->afterTheFirstDraw($source);

        $this->assertStringContainsString(
            'falconToken',
            $handler,
            "{$component} repaints without asking for a token: it will repaint the same colour.",
        );
    }

    /**
     * And none of them watches the document by itself any more.
     *
     * Six watchers were six chances to drift, and three of them had. What
     * announces the change is what changed it, and everything listens to that.
     */
    #[DataProvider('drawings')]
    public function test_it_does_not_watch_the_document_by_itself(string $component): void
    {
        $this->assertStringNotContainsString(
            'MutationObserver',
            $this->source($component),
            "{$component} watches the theme on its own again: the announcement is what to listen to.",
        );
    }

    /**
     * And none of them repaints the frame, which is not theirs to repaint.
     *
     * Two copies of one value that a host may redefine is exactly what this
     * whole pass removed. The kit reads its own tokens for the whole page.
     */
    #[DataProvider('drawings')]
    public function test_it_leaves_the_frame_to_the_kit(string $component): void
    {
        $this->assertStringNotContainsString(
            'falconChartColors',
            $this->source($component),
            "{$component} repaints the frame itself: the kit already does it for the page.",
        );
    }

    /**
     * What follows the first `new window.Chart(` — or the whole file for the
     * map, which draws no chart at all.
     */
    private function afterTheFirstDraw(string $source): string
    {
        $drawn = strpos($source, 'new window.Chart(');

        return $drawn === false ? $source : substr($source, $drawn);
    }

    private function source(string $component): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/components/'.$component.'.blade.php',
        );
    }
}
