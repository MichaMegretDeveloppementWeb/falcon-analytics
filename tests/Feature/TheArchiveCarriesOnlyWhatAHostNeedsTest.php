<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * What a host receives, and nothing else · the `git archive` output, with the
 * `export-ignore` rules applied. Whatever enters it by accident is downloaded by
 * every project on every deploy.
 *
 * The list is an allow-list, so a new top-level entry fails the test until it
 * is named here or excluded.
 */
final class TheArchiveCarriesOnlyWhatAHostNeedsTest extends TestCase
{
    /**
     * Everything an application needs to receive, and why each entry ships.
     *
     * @var list<string>
     */
    private const SHIPPED = [
        'CHANGELOG.md',     // what an update asks the host to do
        'README.md',        // the entry point
        'composer.json',    // the manifest
        'composer.lock',    // the author's test environment, for reference
        'config/',          // the publishable settings
        'database/',        // the migrations
        'docs/',            // the documentation is part of the delivery
        'public/',          // the compiled files
        'resources/',       // the views · styles, scripts and the map are excluded
        'routes/',
        'src/',
    ];

    /**
     * What the archive would carry if the working tree were committed now.
     *
     * `git archive` reads a commit, which lacks any file not committed yet, so
     * the working tree is written into a tree of its own through a separate
     * index, and the repository's index is left as it was.
     * `--worktree-attributes` reads an uncommitted `.gitattributes` likewise.
     *
     * @return list<string>
     */
    private function archive(): array
    {
        $root = escapeshellarg(dirname(__DIR__, 2));
        $index = sys_get_temp_dir().DIRECTORY_SEPARATOR.'falcon-archive-index-'.getmypid();
        $added = 1;
        $written = 1;
        $tree = '';

        putenv("GIT_INDEX_FILE={$index}");

        try {
            exec("git -C {$root} add --all 2>&1", $unused, $added);
            $tree = trim((string) exec("git -C {$root} write-tree 2>&1", $unused, $written));
        } finally {
            putenv('GIT_INDEX_FILE');

            if (is_file($index)) {
                unlink($index);
            }
        }

        $output = [];
        $status = 0;
        exec(sprintf('git -C %s archive --worktree-attributes %s 2>&1 | tar -t 2>&1', $root, escapeshellarg($tree)), $output, $status);

        if ($added !== 0 || $written !== 0 || $status !== 0 || $output === []) {
            $this->markTestSkipped('git ou tar indisponible : la composition de l’archive ne peut pas être lue.');
        }

        return $output;
    }

    /** @return list<string> */
    private function archiveEntries(): array
    {
        $top = [];

        foreach ($this->archive() as $path) {
            $position = strpos($path, '/');
            $top[] = $position === false ? $path : substr($path, 0, $position + 1);
        }

        $top = array_values(array_unique($top));
        sort($top);

        return $top;
    }

    public function test_it_ships_exactly_what_a_host_needs(): void
    {
        $expected = self::SHIPPED;
        sort($expected);

        $this->assertSame(
            $expected,
            $this->archiveEntries(),
            'L’archive a changé de composition. Ajoutez l’entrée à la liste ci-dessus si un hôte en a '
            .'besoin, sinon à `export-ignore` dans .gitattributes.',
        );
    }

    /**
     * Shipped, the style and script sources could not serve · the stylesheet
     * reaches the kit's layers and theme by a relative path that leads nowhere
     * once the package sits among a host's dependencies.
     */
    public function test_it_never_ships_the_build_chain_or_the_bench(): void
    {
        $output = $this->archive();

        foreach (['resources/css/', 'resources/js/', 'resources/svg/', 'tests/', 'scripts/', 'node_modules/'] as $absent) {
            $this->assertEmpty(
                array_filter($output, fn (string $path): bool => str_starts_with($path, $absent)),
                "L’archive porte {$absent}, qu’un hôte n’a aucune raison de recevoir.",
            );
        }
    }
}
