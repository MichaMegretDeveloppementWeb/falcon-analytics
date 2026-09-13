<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What this package promises, and what it merely happens to contain.
 *
 * **Everything public is a commitment.** A class, a method, a table name · once
 * someone outside can name it, it cannot change without breaking them. So what
 * is internal has to say so, and the saying is what this test keeps.
 *
 * The list below is the whole promise. Anything else in `src/` carries
 * `@internal`, and this refuses a class that is neither — the one added in six
 * months that chose nothing, which is exactly the case nobody notices.
 *
 * **It refuses the other direction too.** A promised class that also says it is
 * internal tells two stories, and whoever reads only one of them is misled.
 *
 * ---
 *
 * **How each of the sixteen got on the list**, because the reasoning is what
 * makes it maintainable and the outcome alone would not ·
 *
 * The **documentation names four of them** · a host writes
 * `use Falcon\Analytics\Facades\Analytics;` to record an event,
 * `Events\TrackedEvent` to declare its named events, and `Funnels\Funnel` with
 * `Funnels\FunnelBranch` to declare its funnels. What a notice shows being
 * typed is promised by that fact alone.
 *
 * The **manager behind the facade** goes with it · the facade is a doorway, and
 * the signatures a caller relies on are its.
 *
 * The **service provider** is named in the manifest, and Laravel discovers it
 * there.
 *
 * The **eight models** are public because **a table name is public**. A host
 * ends up writing a query against them sooner or later — a report, an export, a
 * cleanup — and the columns it reads are a promise whether or not anyone meant
 * them to be.
 *
 * The **two enumerations** are cast on a public model · read `$event->type` and
 * you hold an `EventType`, so it is part of the model's surface. `GeoStatus` is
 * not on the list · nothing public returns it.
 *
 * Everything else is the inside · the screens, what feeds them, what reads and
 * writes, what the commands do. **The screens deserve their own word.** The
 * provider announces them to Laravel under `analytics::…`, which would let a
 * host drop one into a page of its own. That is plumbing for our own pages, not
 * an invitation · a screen expects a whole page around it, and what this
 * package promises are the addresses of its pages, never their insides.
 *
 * No database here: it reads files, and nothing else.
 */
final class TheSurfaceIsDeclaredTest extends TestCase
{
    /**
     * The whole promise, and the only place it is written.
     *
     * @var list<string>
     */
    private const PUBLIC_SURFACE = [
        'Analytics',
        'AnalyticsServiceProvider',
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

        foreach ($this->classes() as $name => $source) {
            if (in_array($name, self::PUBLIC_SURFACE, true)) {
                continue;
            }

            if (! str_contains($source, '@internal')) {
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

    /** And nothing promised contradicts itself by also saying it is internal. */
    public function test_nothing_promised_says_it_is_internal(): void
    {
        $contradictory = [];

        foreach ($this->classes() as $name => $source) {
            if (in_array($name, self::PUBLIC_SURFACE, true) && str_contains($source, '@internal')) {
                $contradictory[] = $name;
            }
        }

        $this->assertSame([], $contradictory, 'These are on the promise and say they are internal.');
    }

    /**
     * And the promise names nothing that has ceased to exist.
     *
     * A list nobody checks is a list that outlives what it describes, and a
     * removed class would quietly widen the surface rather than narrow it.
     */
    public function test_the_promise_names_only_classes_that_exist(): void
    {
        $found = array_keys($this->classes());

        foreach (self::PUBLIC_SURFACE as $promised) {
            $this->assertContains($promised, $found, "The promise names {$promised}, which is gone.");
        }
    }

    /**
     * Every class of `src/`, by its name under the package's namespace.
     *
     * @return array<string, string>
     */
    private function classes(): array
    {
        // Forward slashes on both sides before anything is cut away · on
        // Windows the iterator hands back backslashes, which are also the
        // namespace separator we are building.
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

            $classes[$name] = (string) file_get_contents($file->getPathname());
        }

        return $classes;
    }
}
