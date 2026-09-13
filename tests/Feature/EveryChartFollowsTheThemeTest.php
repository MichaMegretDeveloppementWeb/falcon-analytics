<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The six drawings of the package name their colours and resolve none of them.
 *
 * **The division of labour, and it took three goes to land on it.** This package
 * knows which colour a line carries, and declares both versions of it — one per
 * theme — in its own stylesheet, exactly as it declares a class. The kit knows
 * which theme is in force and where to read it. Neither needs the other's half.
 *
 * So a component writes a NAME:
 *
 *     borderColor: 'var(--an-series-1)'
 *
 * and the kit turns it into a value before every draw, reading at the canvas.
 * Nothing here knows whether the page is light or dark, and nothing here has to
 * be told to look again after a switch.
 *
 * What it replaced, on 2026-09-13, was the same watcher written six times and
 * drifted three ways, then a resolution the component had to perform itself and
 * a test standing over it to check the habit. A test that watches a habit is an
 * admission that the work sits in the wrong place; this one watches a contract.
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

    /**
     * Nothing here turns a name into a value.
     *
     * That is the kit's half, and doing it here means knowing what the kit
     * knows: that a canvas needs resolving at all, and where to read so the
     * answer matches the theme in force. Both were got wrong, in that order.
     */
    #[DataProvider('drawings')]
    public function test_it_resolves_no_colour_itself(string $component): void
    {
        $source = $this->source($component);

        foreach (['falconToken', 'falconChartColors', 'getComputedStyle'] as $reading) {
            $this->assertStringNotContainsString(
                $reading,
                $source,
                "{$component} turns a token into a value itself: name it and let the kit answer.",
            );
        }
    }

    /** And nothing here watches the theme, because there is nothing to redo. */
    #[DataProvider('drawings')]
    public function test_it_watches_nothing(string $component): void
    {
        $source = $this->source($component);

        foreach (['MutationObserver', 'theme-changed'] as $watching) {
            $this->assertStringNotContainsString(
                $watching,
                $source,
                "{$component} follows the theme by itself: the kit reads the names again on every draw.",
            );
        }
    }

    /**
     * What it does say is a name, and one the theme sheet actually declares.
     *
     * A name nobody declared resolves to an empty string, and an empty string is
     * a line that simply does not appear — with nothing in the console to say so.
     */
    #[DataProvider('drawings')]
    public function test_every_name_it_writes_is_declared(string $component): void
    {
        $source = $this->source($component);

        // Three ways a name appears: written into the chart, handed to the fade,
        // or standing as the default of a prop the view may override.
        preg_match_all('/var\(\s*(--[\w-]+)\s*\)/', $source, $inline);
        preg_match_all('/(--[\w-]+)/', $this->propsOf($source), $declaredAsDefault);

        $named = array_unique([...$inline[1], ...$declaredAsDefault[1]]);

        // The two doughnuts and the map are handed their names by the view, so
        // there is nothing written here to check beyond the surface colour.
        $this->assertNotSame([], $named, "{$component} names no colour at all.");

        $ofThePackage = (string) file_get_contents($this->packagePath('resources/css/theme.css'));
        $ofTheKit = (string) file_get_contents($this->packagePath('vendor/falcon/ui-kit/resources/css/ui.css'));

        foreach ($named as $token) {
            $this->assertTrue(
                str_contains($ofThePackage, $token.':') || str_contains($ofTheKit, $token.':'),
                "{$component} names {$token}, which neither the package nor the kit declares.",
            );
        }
    }

    /**
     * And the names reach the chart as names.
     *
     * A component that named a token and then handed the chart something else
     * would pass everything above and still draw the wrong colour.
     */
    #[DataProvider('drawings')]
    public function test_it_hands_the_chart_names_rather_than_values(string $component): void
    {
        $source = $this->source($component);

        $this->assertSame(
            0,
            preg_match('/#[0-9a-fA-F]{3,8}\b/', $source),
            "{$component} hands over a colour written by hand.",
        );
    }

    /** The `@props` block, where a colour the view may override has its default. */
    private function propsOf(string $source): string
    {
        preg_match('/@props\(\[(.*?)\]\)/s', $source, $found);

        return $found[1] ?? '';
    }

    private function source(string $component): string
    {
        return (string) file_get_contents(
            $this->packagePath('resources/views/components/'.$component.'.blade.php'),
        );
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
