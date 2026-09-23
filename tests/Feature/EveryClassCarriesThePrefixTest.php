<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * No class of the package goes out without its prefix.
 *
 * Two different names do not fight, so the package's stylesheet shares a page
 * with the kit's and the host's. A forgotten class is not generated at all: the
 * generator only knows the prefixed names, so the element draws unstyled and
 * nothing says so.
 *
 * It does not read comments: a `bg-white` quoted in an explanation draws
 * nothing.
 *
 * No database here, hence no `TestCase` of the package: it reads files, and
 * nothing else.
 */
final class EveryClassCarriesThePrefixTest extends TestCase
{
    /**
     * Shapes that belong to Tailwind alone, and so cannot be anything other
     * than a forgotten class; the variants come first.
     *
     * A shape missing here goes uncaught, so the list covers every family the
     * views use. `cursor-` is absent: the kit has an icon named
     * `cursor-arrow-rays`, and no shape tells an icon name from a class.
     *
     * @var list<string>
     */
    private const SHAPES = [
        'dark:', 'hover:', 'focus:', 'focus-within:', 'group-hover:', 'disabled:', 'peer-checked:',
        'sm:', 'md:', 'lg:', 'xl:', 'wide:', 'max-md:', 'last:', 'first:', 'placeholder:',
        'bg-', 'text-', 'border-', 'rounded-', 'ring-', 'shadow-', 'divide-', 'fill-', 'stroke-',
        'px-', 'py-', 'pt-', 'pb-', 'pl-', 'pr-', 'p-',
        'mx-', 'my-', 'mt-', 'mb-', 'ml-', 'mr-',
        'gap-', 'gap-x-', 'gap-y-', 'space-x-', 'space-y-',
        'w-', 'h-', 'min-w-', 'min-h-', 'max-w-', 'max-h-',
        'flex-', 'items-', 'justify-', 'grid-cols-', 'col-span-', 'row-span-',
        'shrink-', 'grow-', 'order-', 'self-', 'place-', 'content-', 'object-', 'aspect-',
        'font-', 'leading-', 'tracking-', 'align-', 'whitespace-', 'break-', 'truncate-',
        'opacity-', 'overflow-', 'transition-', 'animate-', 'backdrop-', 'tabular-',
        'inset-', 'top-', 'bottom-', 'left-', 'right-', 'z-', 'pointer-events-',
    ];

    public function test_no_view_writes_an_unprefixed_class(): void
    {
        $found = [];

        foreach ($this->views() as $name => $file) {
            $source = $this->withoutComments((string) file_get_contents($file));

            preg_match_all('/[A-Za-z0-9:_.\/%\[\]#!-]+/', $source, $matches);

            foreach ($matches[0] as $token) {
                if ($this->looksLikeAForgottenClass($token)) {
                    $found[] = "{$name} · {$token}";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($found)),
            "These classes have no prefix. They will not be generated, and the element\n"
            .'will draw unstyled, without the slightest error. Write them `an:…`.',
        );
    }

    /**
     * Names that carry the shape of a utility without being one.
     *
     * These are SVG presentation attributes, written on a `<path>` or passed to
     * a kit component: `stroke-width="2.5"`. Prefixing them would break them,
     * and ignoring them hides nothing — a real class of the same family is
     * written `fill-gray-800`, never `fill-opacity`.
     *
     * @var list<string>
     */
    private const SVG_ATTRIBUTES = [
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity', 'stroke-dasharray',
        'fill-opacity', 'fill-rule', 'fill-box',
    ];

    private function looksLikeAForgottenClass(string $token): bool
    {
        // Already prefixed, or a hook class written by hand.
        if (str_starts_with($token, 'an:') || str_starts_with($token, 'an-')) {
            return false;
        }

        if (in_array($token, self::SVG_ATTRIBUTES, true)) {
            return false;
        }

        foreach (self::SHAPES as $shape) {
            if (str_starts_with($token, $shape)) {
                return true;
            }
        }

        return false;
    }

    /** A comment never drew anything, so it leaves the field. */
    private function withoutComments(string $source): string
    {
        foreach (['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '/\/\*.*?\*\//s', '/(^|\s)\/\/[^\n]*/m'] as $shape) {
            $source = (string) preg_replace($shape, ' ', $source);
        }

        return $source;
    }

    /** @return array<string, string> */
    private function views(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $found = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = $file->getPathname();
            }
        }

        ksort($found);

        return $found;
    }
}
