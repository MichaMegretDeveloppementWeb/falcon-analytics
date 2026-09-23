<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The six drawings of the package name their colours and resolve none of them.
 *
 * The package declares both versions of each colour, one per theme, in its own
 * stylesheet; the kit knows which theme is in force and where to read it. So a
 * component writes a NAME:
 *
 *     borderColor: 'var(--an-series-1)'
 *
 * and the kit turns it into a value before every draw, reading at the canvas.
 * Nothing here knows whether the page is light or dark.
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

    /** Resolving a name is the kit's half: it knows where to read so the value matches the theme. */
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

    /** The kit reads the names again on every draw, so there is nothing to redo. */
    #[DataProvider('drawings')]
    public function test_it_watches_nothing(string $component): void
    {
        $source = $this->source($component);

        foreach (['MutationObserver', 'ui-theme-changed'] as $watching) {
            $this->assertStringNotContainsString(
                $watching,
                $source,
                "{$component} follows the theme by itself: the kit reads the names again on every draw.",
            );
        }
    }

    /** An undeclared name resolves to an empty string: a line that silently does not appear. */
    #[DataProvider('drawings')]
    public function test_every_name_it_writes_is_declared(string $component): void
    {
        $source = $this->source($component);

        // A name appears inline, in the fade, or as a prop default the view may override.
        preg_match_all('/var\(\s*(--[\w-]+)\s*\)/', $source, $inline);
        preg_match_all('/(--[\w-]+)/', $this->propsOf($source), $declaredAsDefault);

        $named = array_unique([...$inline[1], ...$declaredAsDefault[1]]);

        // The doughnuts and the map receive their names from the view, beyond the surface colour.
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

    /** A component could name a token, hand the chart a literal, and still pass every test above. */
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

    /**
     * The view, the module that draws it and the parts the charts share, read
     * as one: a colour can be named in any of them.
     */
    private function source(string $component): string
    {
        return implode("\n", array_map(
            fn (string $path): string => (string) file_get_contents($this->packagePath($path)),
            [
                'resources/views/components/'.$component.'.blade.php',
                'resources/js/admin/components/'.$component.'.js',
                'resources/js/admin/components/chart-parts.js',
            ],
        ));
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
