<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\ChartPalette;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * No colour is written down twice, because no colour is written down at all.
 *
 * `paquet-style.md` §4 asks for a named, documented token for every value a
 * host may want to change, and a chart is exactly that: it is drawn on a canvas
 * by Chart.js, so no class reaches it and the values had been written by hand —
 * in both themes, in every view that drew something.
 *
 * **The drift was already there when this was measured**, on 2026-09-12. Four
 * views declared what was meant to be one ramp of blues and three disagreed:
 * `#4b9bf0` against `#54a8f0` on the second step, `#a5cdf7` against `#a5cdf6`
 * on the fourth, `#d1d5db` against `#d7e9fc` on the sixth. Nobody decided any
 * of it. A copy aged, and nothing could say so.
 *
 * 25 distinct values, 146 times, across 19 views and one PHP class. This test
 * is what keeps the next one from being written.
 *
 * No database here: it reads files, and nothing else.
 */
final class EveryColourIsATokenTest extends TestCase
{
    /**
     * A hex colour anywhere in the delivered source.
     *
     * Comments are read too, and deliberately: a value quoted in an
     * explanation is a value that will be copied out of it one day.
     *
     * Not after `&` or `amp;`, which make it an HTML entity · `&#039;` and its
     * doubly escaped form `&amp;#039;` are the escaped apostrophe a docblock
     * quotes when explaining a twice-escaped title, and neither has ever
     * coloured anything. Excluding on what follows would not do: a real colour
     * is very often followed by the `;` that ends a declaration.
     */
    private const COLOUR = '/(?<!&)(?<!amp;)#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/';

    public function test_no_view_writes_a_colour(): void
    {
        $this->assertSame([], $this->coloursIn('resources/views', '.blade.php'));
    }

    /**
     * And no PHP either. A ramp lived in a Livewire component as a class
     * constant, which the view sweep could not see — it was found by accident,
     * and it was a fifth ramp.
     */
    public function test_no_class_writes_a_colour(): void
    {
        $this->assertSame([], $this->coloursIn('src', '.php'));
    }

    /**
     * The one place they are allowed, and the shape that makes them readable.
     *
     * Every token holding a literal is declared twice, once per theme. A token
     * declared once would be a token that ignores the dark mode — which is
     * exactly the bug the literals had, written as a second set of `dark:`
     * classes beside them.
     *
     * A token holding `var(…)` derives from another and is declared once on
     * purpose: it follows whatever its source does, in both themes.
     */
    public function test_every_literal_token_is_declared_in_both_themes(): void
    {
        $theme = (string) file_get_contents($this->packagePath('resources/css/theme.css'));

        preg_match_all('/(--an-[a-z0-9-]+):\s*(#[0-9a-fA-F]+|var\()/', $theme, $matches, PREG_SET_ORDER);

        $literal = [];

        foreach ($matches as [, $token, $value]) {
            if (str_starts_with($value, '#')) {
                $literal[$token] = ($literal[$token] ?? 0) + 1;
            }
        }

        $this->assertNotSame([], $literal, 'The theme declares no token at all.');

        foreach ($literal as $token => $count) {
            $this->assertSame(2, $count, "{$token} must be declared once for each theme.");
        }
    }

    /** The ramp is named in one place, and the views take it from there. */
    public function test_the_ramp_is_declared_once(): void
    {
        $theme = (string) file_get_contents($this->packagePath('resources/css/theme.css'));

        foreach (ChartPalette::SERIES as $token) {
            $this->assertStringContainsString(
                $token.':',
                $theme,
                "{$token} is named by the palette but declared nowhere.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function coloursIn(string $directory, string $suffix): array
    {
        $root = $this->packagePath($directory);
        $found = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), $suffix)) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // `preg_split` answers `false` on a broken pattern. This one is
            // not, but the signature says so and a file is never empty here.
            $lines = preg_split('/\R/', $source);

            foreach ($lines === false ? [] : $lines as $number => $line) {
                if (preg_match(self::COLOUR, $line) === 1) {
                    $name = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    $found[] = $name.':'.($number + 1);
                }
            }
        }

        sort($found);

        return $found;
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
