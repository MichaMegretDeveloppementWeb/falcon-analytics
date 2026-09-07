<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Charts must wear the host's font, not one this package picked.
 *
 * Canvas text is drawn by Chart.js, so a theme's `--font-sans` never reaches
 * it. Four components here named 'DM Sans' across ticks, legend and tooltips —
 * eleven literals in all. On a host that had chosen another family, every
 * chart drew in a font the page carried nowhere. Nothing failed, and nothing
 * said so.
 *
 * falcon/ui-kit now sets the page font as Chart.js's default when it hands
 * over the library, so naming nothing is what makes a chart correct. An
 * explicit family would win over that default, which is precisely the bug.
 */
final class ChartFontTest extends TestCase
{
    public function test_it_names_no_font_family_in_any_view_so_the_kit_default_is_the_one_drawn(): void
    {
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../resources/views', FilesystemIterator::SKIP_DOTS),
        );

        $offenders = [];

        foreach ($views as $view) {
            if (! str_ends_with($view->getPathname(), '.blade.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($view->getPathname()), 'family:')) {
                $offenders[] = $view->getBasename();
            }
        }

        $this->assertSame([], $offenders, 'These views hard-code a font family: '.implode(', ', $offenders));
    }
}
