<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every name the package puts in the page carries its mark.
 *
 * **A utility prefix renames classes and nothing else.** An event name keeps
 * whatever it was called, and two providers that pick the same one warn nobody:
 * both listeners answer each other's calls.
 *
 * Adding a bare name works perfectly here and only fails at somebody else's,
 * which is why this is read rather than trusted.
 *
 * **The tracking attributes are not read here, and that is deliberate.**
 * `data-track-event` and its neighbours are not names this package gave itself:
 * they are the vocabulary a host writes on its own pages to declare what it
 * wants counted. A readable name is the point of them.
 */
final class ThePackageMarksWhatItExposesTest extends TestCase
{
    /**
     * Names the package listens to rather than declares · the browser's, and
     * those the two frameworks announce themselves under.
     *
     * @var list<string>
     */
    private const NOT_OURS_TO_NAME = [
        'beforeunload', 'change', 'click', 'DOMContentLoaded', 'focusin', 'focusout',
        'input', 'keydown', 'keyup', 'load', 'message', 'mouseout', 'mouseover',
        'pagehide', 'pageshow', 'pointerdown', 'popstate', 'resize', 'scroll',
        'submit', 'touchend', 'touchmove', 'touchstart', 'transitionend',
        'visibilitychange',
        'alpine:init', 'alpine:initialized', 'livewire:init', 'livewire:initialized',
    ];

    /**
     * What a name of the suite looks like, whatever kind it is.
     *
     * The libraries are here because they answer to their own names on the
     * page, and the package only calls them.
     *
     * @var list<string>
     */
    private const MARKED = ['an-', 'ui-', 'falcon', 'Alpine', 'Chart', 'Livewire'];

    /** The names the package shouts into the page, and those it listens for. */
    public function test_no_event_is_named_without_a_mark(): void
    {
        $bare = [];

        foreach ($this->scripts() as $file) {
            preg_match_all(
                "/(?:new CustomEvent|addEventListener|removeEventListener|\\\$dispatch)\(\s*'([a-zA-Z][a-zA-Z0-9:_-]*)'/",
                (string) file_get_contents($file->getPathname()),
                $found,
            );

            $bare = array_merge($bare, $this->unmarked($file, $found[1]));
        }

        foreach ($this->views() as $file) {
            $source = (string) file_get_contents($file->getPathname());

            // What a view emits, and what it binds a listener to · Alpine
            // writes the second as `@name.window` or `x-on:name.window`.
            preg_match_all("/\\\$dispatch\(\s*'([a-zA-Z][a-zA-Z0-9:_-]*)'/", $source, $emitted);
            preg_match_all('/(?:@|x-on:)([a-z][a-zA-Z0-9:_-]*)\.window/', $source, $heard);

            $bare = array_merge($bare, $this->unmarked($file, array_merge($emitted[1], $heard[1])));
        }

        foreach ($this->classes() as $file) {
            preg_match_all(
                "/(?:->dispatch|#\[On)\(\s*'([a-zA-Z][a-zA-Z0-9:_-]*)'/",
                (string) file_get_contents($file->getPathname()),
                $found,
            );

            $bare = array_merge($bare, $this->unmarked($file, $found[1]));
        }

        $this->assertSame(
            [],
            array_values(array_unique($bare)),
            "These event names carry no mark:\n  "
            .implode("\n  ", array_unique($bare))
            ."\n\nName them `an-…`, or `ui-…` when they belong to the kit. A prefix of "
            .'utilities does not reach an event name.',
        );
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function unmarked(SplFileInfo $file, array $names): array
    {
        $bare = [];

        foreach ($names as $name) {
            if ($this->isMarked($name) || in_array($name, self::NOT_OURS_TO_NAME, true)) {
                continue;
            }

            $bare[] = $file->getFilename().' · '.$name;
        }

        return $bare;
    }

    private function isMarked(string $name): bool
    {
        foreach (self::MARKED as $mark) {
            if (str_starts_with($name, $mark)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<SplFileInfo> */
    private function scripts(): array
    {
        return $this->filesUnder('/resources/js', '.js');
    }

    /** @return list<SplFileInfo> */
    private function views(): array
    {
        return $this->filesUnder('/resources/views', '.blade.php');
    }

    /** @return list<SplFileInfo> */
    private function classes(): array
    {
        return $this->filesUnder('/src', '.php');
    }

    /** @return list<SplFileInfo> */
    private function filesUnder(string $relative, string $suffix): array
    {
        $found = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2).$relative, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), $suffix)) {
                $found[] = $file;
            }
        }

        $this->assertNotSame([], $found, 'No file was read under '.$relative.': the path is wrong.');

        return $found;
    }
}
