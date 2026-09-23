<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every dialog the package opens is the kit's modal.
 *
 * A dialog drawn by hand looks the same and is not one: the keyboard stays on
 * the page behind the backdrop, Tab walks what the backdrop hides, and a screen
 * reader hears no dialog at all. The kit's modal holds the keyboard, hands the
 * focus back and says what it is.
 *
 * No database here: it reads files, and nothing else.
 */
final class EveryDialogIsTheKitsTest extends TestCase
{
    public function test_no_view_draws_its_own_backdrop(): void
    {
        foreach ($this->views() as $view => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '/an:fixed an:inset-0/',
                $source,
                "{$view} draws a full-screen layer of its own: a dialog is `<x-ui::modal>`.",
            );
        }
    }

    /** The open state lives in the kit's modal and nowhere else. */
    public function test_no_view_opens_a_dialog_from_server_state(): void
    {
        foreach ($this->views() as $view => $source) {
            $this->assertStringNotContainsString(
                '$wire.modal',
                $source,
                "{$view} opens a dialog from a server property: it opens on the server's answer instead.",
            );
        }
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
