<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every Alpine component and magic a view names is registered, under the
 * package's prefix.
 *
 * Alpine says nothing about a name it does not know: the element simply does
 * nothing. And a name is global to the page, shared with the host and every
 * other package · an unprefixed one would answer to whichever registered it
 * last.
 *
 * No database here: it reads files, and nothing else.
 */
final class EveryAlpineNameIsRegisteredTest extends TestCase
{
    public function test_every_name_a_view_uses_is_registered(): void
    {
        $registered = $this->registered();

        foreach ($this->used() as $name => $view) {
            $this->assertContains(
                $name,
                $registered,
                "{$view} names {$name}, which analytics-admin.js does not register: the element would do nothing.",
            );
        }
    }

    public function test_every_registered_name_carries_the_prefix(): void
    {
        foreach ($this->registered() as $name) {
            $this->assertMatchesRegularExpression(
                '/^an[A-Z]/',
                $name,
                "{$name} is registered without the package's prefix: another script could take it.",
            );
        }
    }

    public function test_every_registered_name_is_used(): void
    {
        $used = array_keys($this->used());

        foreach ($this->registered() as $name) {
            $this->assertContains($name, $used, "{$name} is registered and no view names it.");
        }
    }

    /**
     * The names the entry hands to `Alpine.data` and `Alpine.magic`, read from
     * its two tables.
     *
     * @return list<string>
     */
    private function registered(): array
    {
        $entry = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/analytics-admin.js');
        $registered = [];

        foreach (['components', 'magics'] as $kind) {
            $this->assertSame(1, preg_match('/const '.$kind.' = \{(.*?)\};/s', $entry, $table), "The table of {$kind} moved.");

            preg_match_all('/^\s*(\w+),\s*$/m', $table[1], $names);

            $this->assertNotSame([], $names[1], "No name was read from the {$kind}: the guard would watch nothing.");

            $registered = [...$registered, ...$names[1]];
        }

        return $registered;
    }

    /**
     * The components and magics the views name, each with a view that names it.
     *
     * @return array<string, string>
     */
    private function used(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $used = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all('/\sx-data="([a-zA-Z]\w*)/', $contents, $components);
            preg_match_all('/\$(an[A-Z]\w*)\(/', $contents, $magics);

            foreach ([...$components[1], ...$magics[1]] as $name) {
                $used[$name] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        return $used;
    }
}
