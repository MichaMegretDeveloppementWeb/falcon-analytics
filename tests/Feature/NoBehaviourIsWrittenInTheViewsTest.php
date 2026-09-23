<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A view names its behaviour; it never holds it.
 *
 * What is written in an attribute is read by no linter, no formatter and no
 * test. So an `x-data` holds state or names a registered component, and an
 * event attribute holds one expression · anything longer lives in
 * `resources/js/`, where it is read.
 *
 * No database here: it reads files, and nothing else.
 */
final class NoBehaviourIsWrittenInTheViewsTest extends TestCase
{
    /** A method, a function, an arrow or a second instruction. */
    private const LOGIC = '/;|=>|\bfunction\b|\basync\b|\bget\s+\w+\s*\(|\b\w+\s*\([^()]*\)\s*\{/';

    /**
     * A native event attribute, whatever it holds.
     *
     * Written without the equals sign a browser needs, so this file does not
     * trip the guard it carries.
     */
    private const NATIVE_EVENT = '/\son[a-z]+[\s]*=/';

    public function test_no_x_data_holds_logic(): void
    {
        foreach ($this->views() as $view => $source) {
            preg_match_all('/\sx-data="([^"]*)"/', $source, $found);

            foreach ($found[1] as $object) {
                $this->assertDoesNotMatchRegularExpression(
                    self::LOGIC,
                    $this->withoutBlade($object),
                    "{$view} writes behaviour in an x-data: move it to a component in resources/js/.",
                );
            }
        }
    }

    public function test_no_event_attribute_holds_more_than_one_expression(): void
    {
        foreach ($this->views() as $view => $source) {
            preg_match_all('/\s(?:@[\w.:-]+|x-on:[\w.:-]+|x-init|x-effect)="([^"]*)"/', $source, $found);

            foreach ($found[1] as $expression) {
                $this->assertDoesNotMatchRegularExpression(
                    '/[;{]/',
                    $this->withoutBlade($expression),
                    "{$view} writes more than one expression in an attribute: make it a method.",
                );
            }
        }
    }

    /**
     * The two guards above read what an attribute contains, so a short enough
     * handler slips through both. A native handler is also invisible to the
     * behaviour registry and unreachable by keyboard, so it is refused whatever
     * it holds.
     */
    public function test_no_view_carries_a_native_event_attribute(): void
    {
        foreach ($this->views() as $view => $source) {
            $this->assertDoesNotMatchRegularExpression(
                self::NATIVE_EVENT,
                $source,
                "{$view} carries a native event attribute: name the behaviour in resources/js/, and make what answers a click a link or a button.",
            );
        }
    }

    /**
     * Each pattern matches the shapes it refuses and lets through the ones it
     * accepts, so a pattern that matched nothing would not pass for a clean package.
     */
    public function test_the_guard_tells_logic_from_state(): void
    {
        $this->assertMatchesRegularExpression(self::NATIVE_EVENT, '<tr '.'onclick="go()">');
        $this->assertMatchesRegularExpression(self::NATIVE_EVENT, '<input '.'onchange="x">');
        $this->assertDoesNotMatchRegularExpression(self::NATIVE_EVENT, '<button x-on:click="go()">');
        $this->assertDoesNotMatchRegularExpression(self::NATIVE_EVENT, '<button wire:click="go">');

        $this->assertMatchesRegularExpression(self::LOGIC, '{ open: false, toggle() { this.open = ! this.open } }');
        $this->assertMatchesRegularExpression(self::LOGIC, '{ async save() { await $wire.save() } }');
        $this->assertDoesNotMatchRegularExpression(self::LOGIC, "{ open: false, search: '' }");
        $this->assertDoesNotMatchRegularExpression(self::LOGIC, $this->withoutBlade('anDonut({ labels: @js(array_values($labels)) })'));
    }

    /** Blade's own syntax, which reaches the browser as a value and not as code. */
    private function withoutBlade(string $attribute): string
    {
        $attribute = (string) preg_replace('/\{\{.*?\}\}/s', '', $attribute);

        return (string) preg_replace('/@js\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', 'null', $attribute);
    }

    /**
     * @return array<string, string>
     */
    private function views(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $views = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $views[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }

        $this->assertNotSame([], $views, 'No view was read: the guard would watch nothing.');

        return $views;
    }
}
