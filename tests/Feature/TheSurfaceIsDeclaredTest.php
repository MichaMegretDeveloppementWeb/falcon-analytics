<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * What this package promises, and what it merely happens to contain.
 *
 * Everything public is a commitment, so what is internal says so · every class
 * of `src/` is either on the list below or carries `@internal`, never both.
 *
 * No database here: it reads files, and nothing else.
 */
final class TheSurfaceIsDeclaredTest extends TestCase
{
    /**
     * The whole promise, and the only place it is written · what the
     * documentation shows a host typing, the manager behind the facade, the
     * provider the manifest names, the models, since a table name is public,
     * and the enumerations cast on them. The screens registered under
     * `analytics::…` serve the package's own pages and are not promised.
     *
     * @var list<string>
     */
    private const PUBLIC_SURFACE = [
        'Analytics',
        'AnalyticsServiceProvider',
        'Enums\Authorization\Ability',
        'Enums\EventType',
        'Enums\ObjectiveType',
        'Events\TrackedEvent',
        'Facades\Analytics',
        'Funnels\Funnel',
        'Funnels\FunnelBranch',
        'Models\Ad',
        'Models\AdObjective',
        'Models\Campaign',
        'Models\Event',
        'Models\SearchConsoleConnection',
        'Models\SearchQuery',
        'Models\Session',
        'Models\Visitor',
    ];

    public function test_everything_outside_the_promise_says_it_is_internal(): void
    {
        $silent = [];

        foreach ($this->classes() as $name => $saysInternal) {
            if (in_array($name, self::PUBLIC_SURFACE, true)) {
                continue;
            }

            if (! $saysInternal) {
                $silent[] = $name;
            }
        }

        $this->assertSame(
            [],
            $silent,
            'These classes are neither promised nor said to be internal, so they are promised by '
            ."default:\n  ".implode("\n  ", $silent),
        );
    }

    public function test_nothing_promised_says_it_is_internal(): void
    {
        $contradictory = [];

        foreach ($this->classes() as $name => $saysInternal) {
            if (in_array($name, self::PUBLIC_SURFACE, true) && $saysInternal) {
                $contradictory[] = $name;
            }
        }

        $this->assertSame([], $contradictory, 'These are on the promise and say they are internal.');
    }

    /**
     * A list nobody checks outlives what it describes, and a removed class left
     * on it would quietly widen the surface.
     */
    public function test_the_promise_names_only_classes_that_exist(): void
    {
        $found = array_keys($this->classes());

        foreach (self::PUBLIC_SURFACE as $promised) {
            $this->assertContains($promised, $found, "The promise names {$promised}, which is gone.");
        }
    }

    /**
     * Every class of `src/`, and whether the class itself says it is internal ·
     * `getDocComment` hands back the block PHP attaches to the class, so an
     * `@internal` in a method's comment does not count. A class the autoloader
     * cannot find under the name its path spells fails here too.
     *
     * @return array<string, bool>
     */
    private function classes(): array
    {
        // Forward slashes first: on Windows the iterator's backslashes are also the namespace separator.
        $root = str_replace('\\', '/', dirname(__DIR__, 2).'/src');

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $classes = [];

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $name = str_replace(
                [$root.'/', '/', '.php'],
                ['', '\\', ''],
                str_replace('\\', '/', $file->getPathname()),
            );

            $full = 'Falcon\\Analytics\\'.$name;

            $this->assertTrue(
                class_exists($full) || interface_exists($full) || trait_exists($full) || enum_exists($full),
                "{$name} is not loadable under the name its path spells: PSR-4 is broken here.",
            );

            $classes[$name] = str_contains(
                (string) (new ReflectionClass($full))->getDocComment(),
                '@internal',
            );
        }

        return $classes;
    }
}
