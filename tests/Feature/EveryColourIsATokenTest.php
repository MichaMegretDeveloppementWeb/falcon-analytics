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
 * Every value a host may want to change is a named token. A chart is drawn on a
 * canvas, where no class reaches, so its colours are tokens declared once in the
 * theme sheet; a literal copied from view to view drifts silently.
 *
 * No database here: it reads files, and nothing else.
 */
final class EveryColourIsATokenTest extends TestCase
{
    /**
     * A hex colour anywhere in the delivered source, comments included: a value
     * quoted in an explanation gets copied out of it.
     *
     * Not after `&` or `amp;`, which make it an HTML entity such as `&#039;` or
     * `&amp;#039;`. A real colour is often followed by the `;` that ends a
     * declaration, so only what precedes tells the two apart.
     */
    private const COLOUR = '/(?<!&)(?<!amp;)#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/';

    public function test_no_view_writes_a_colour(): void
    {
        $this->assertSame([], $this->coloursIn('resources/views', '.blade.php'));
    }

    public function test_no_class_writes_a_colour(): void
    {
        $this->assertSame([], $this->coloursIn('src', '.php'));
    }

    /**
     * The theme sheet is the one place literals are allowed. A literal token
     * declared once would ignore the dark mode; a token holding `var(…)`
     * derives from another and follows it in both themes.
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

            // `preg_split` is typed to return `false` on a broken pattern.
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
