<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What the package's stylesheet owes to those it stands beside on a page.
 *
 * **A package never draws alone.** Its sheet lands on a page where the kit's
 * already lives, the host's too, and sometimes another package's. Four things
 * make it able to share that page, and not one of them shows on screen when it
 * is missing: a single entry, the eight layers taken from the kit, selectors
 * that reach only what this package draws, and custom property names that
 * designate something.
 *
 * The last one is the expensive one. A `var()` that resolves to nothing does
 * not keep the property's previous value: the property is worth NOTHING, and
 * that emptiness is inherited by everything rendered below. Nothing raises, the
 * build succeeds, the sheet keeps its size — only the screen shows it, in one
 * theme.
 */
final class TheStylesheetFollowsTheContractTest extends TestCase
{
    /**
     * One entry compiled by Tailwind, and it is `analytics.css`.
     *
     * Two Tailwind sheets on one page argue over the same layers, and **it is
     * the first declaration met that fixes the order for the whole document**.
     * The second loses without saying so, and its style ends up behind the very
     * style it was meant to redraw.
     */
    public function test_the_package_has_exactly_one_compiled_entry(): void
    {
        $entries = [];

        foreach ($this->stylesheets() as $file) {
            if (str_contains((string) file_get_contents($file->getPathname()), "@import 'tailwindcss")) {
                $entries[] = $file->getFilename();
            }
        }

        $this->assertSame(
            ['analytics.css'],
            $entries,
            'The package must have ONE compiled entry. Two Tailwind sheets on one page argue '
            .'over the layers, and the first one read decides for the whole document.',
        );
    }

    /**
     * Copied layers need only one name or one position drifting for all of this
     * package's style to fall behind the kit's, on every page where it is
     * loaded first. Its redrawn button becomes the kit's again, and nothing
     * raises.
     */
    public function test_the_entry_takes_the_layers_from_the_kit(): void
    {
        $entry = (string) file_get_contents($this->cssRoot().'/analytics.css');

        $this->assertStringContainsString(
            "@import '../../vendor/falcon/ui-kit/resources/css/layers.css';",
            $entry,
            'The eight layers must come from the kit, never from a copy.',
        );

        $this->assertStringNotContainsString(
            '@layer theme, base',
            $entry,
            'The layers are copied into the entry: one drift would be enough to break the '
            .'priority of the whole suite.',
        );
    }

    /**
     * `.an-card td` also takes the cell of a neighbouring package nested there,
     * which then wears a border nobody asked it for. Read on the selectors and
     * never on the comments, which explain such forms in prose.
     */
    public function test_no_selector_reaches_a_bare_element_under_a_root(): void
    {
        $leaks = [];

        foreach ($this->stylesheets() as $file) {
            foreach ($this->selectorsOf($file) as $selector) {
                if ($this->reachesABareElement($selector)) {
                    $leaks[] = $file->getFilename().' · '.$selector;
                }
            }
        }

        $this->assertSame(
            [],
            $leaks,
            "These selectors reach an element this package does not draw:\n  "
            .implode("\n  ", $leaks)
            ."\n\nPut a class of the package on the element, or the kit's hook class paired "
            .'with `[data-ui-scope="analytics"]`.',
        );
    }

    /**
     * `--an-` for the package, `--ui-` for the kit · the prefix on the import of
     * `theme.css` renames the theme's variables too, so `var(--color-gray-800)`
     * designates nothing. Read outside comments, which name that form to explain it.
     */
    public function test_every_custom_property_read_carries_a_prefix_of_the_suite(): void
    {
        $strays = [];

        foreach ($this->stylesheets() as $file) {
            $css = (string) preg_replace(
                '~/\*.*?\*/~s',
                '',
                (string) file_get_contents($file->getPathname()),
            );

            preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)/', $css, $found);

            foreach ($found[1] as $property) {
                if (str_starts_with($property, '--an-') || str_starts_with($property, '--ui-')) {
                    continue;
                }

                $strays[] = $file->getFilename().' · '.$property;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($strays)),
            "These properties designate nothing:\n  "
            .implode("\n  ", array_unique($strays))
            ."\n\nTailwind's palette carries the package's prefix · `--an-color-gray-800` and "
            .'not `--color-gray-800`. A `var()` that fails empties the property, and the '
            .'emptiness is inherited.',
        );
    }

    /**
     * Does a selector descend towards an element the package does not name?
     *
     * The first part is the hook · it says which package we are talking about.
     * What follows must carry a class, without which the rule reaches anything
     * rendered under there. No hook is exempt · exempting `.an-root` would
     * exempt the package whole.
     */
    private function reachesABareElement(string $selector): bool
    {
        $split = preg_split('/\s*[>+~]\s*|\s+/', trim($selector));
        $parts = $split === false ? [] : $split;

        if (count($parts) < 2) {
            return false;
        }

        foreach (array_slice($parts, 1) as $part) {
            // `.an-x`, `.ui-x[data-ui-scope]`, `&`, a lone pseudo-class: each names something other than a tag.
            if (str_contains($part, '.') || str_starts_with($part, ':') || $part === '') {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * A file's selectors, comments and rule bodies removed.
     *
     * @return list<string>
     */
    private function selectorsOf(SplFileInfo $file): array
    {
        $css = (string) preg_replace(
            '~/\*.*?\*/~s',
            '',
            (string) file_get_contents($file->getPathname()),
        );

        // Rule bodies go with their braces, leaving the preludes; at-rules are skipped below.
        $css = (string) preg_replace('/\{[^{}]*\}/s', '{}', $css);

        $preludes = preg_match_all('/([^{}]+)\{\}/', $css, $found) !== false ? $found[1] : [];

        $selectors = [];

        foreach ($preludes as $prelude) {
            foreach ($this->commaSeparated($prelude) as $selector) {
                $selector = trim($selector);

                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }

                $selectors[] = $selector;
            }
        }

        return $selectors;
    }

    /**
     * A prelude's selectors, cut at the commas that REALLY separate.
     *
     * `:not([aria-disabled='true'], :disabled)` carries one, and an `explode`
     * takes it for a separation · it returns two halves, neither of which is a
     * selector.
     *
     * @return list<string>
     */
    private function commaSeparated(string $prelude): array
    {
        $found = [];
        $current = '';
        $depth = 0;

        foreach (str_split($prelude) as $character) {
            if ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;
            }

            if ($character === ',' && $depth === 0) {
                $found[] = $current;
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $found[] = $current;

        return $found;
    }

    /**
     * The package's stylesheets.
     *
     * A third party's would be left out here, as a copy we do not edit: judging
     * it on our rules would call it wrong when it is a written exception with a
     * single owner. This package vendors none.
     *
     * @return list<SplFileInfo>
     */
    private function stylesheets(): array
    {
        $found = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cssRoot(), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! str_ends_with($file->getFilename(), '.css')) {
                continue;
            }

            if (str_contains(str_replace('\\', '/', $file->getPathname()), '/css/vendor/')) {
                continue;
            }

            $found[] = $file;
        }

        $this->assertNotSame([], $found, 'No stylesheet was read: the path is wrong.');

        return $found;
    }

    private function cssRoot(): string
    {
        return dirname(__DIR__, 2).'/resources/css';
    }
}
