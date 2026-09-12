<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Does what the package ships match the sources that produce it?
 *
 * The package compiles and ships compiled files: a stylesheet and a script. An
 * application publishes and serves them without ever rebuilding them, so a
 * stale file gives it badly drawn screens without a single message.
 *
 * The mistake is easy to make: you change a view, you see it work locally
 * because you have just compiled, and you push without replaying the build.
 *
 * **The views count as much as the CSS.** The utility generator reads them: a
 * class added to a screen changes the shipped stylesheet as surely as a
 * hand-written rule does.
 *
 * The fingerprint is written by `scripts/fingerprint.mjs`, called by
 * `npm run build`. What this test cannot say is whether the build produces the
 * same file twice: that needs node, and it is the job of
 * `npm run check-assets`.
 *
 * No database here: it reads files, and nothing else.
 */
final class AssetsAreUpToDateTest extends TestCase
{
    public function test_the_shipped_files_exist(): void
    {
        foreach (['analytics.css', 'analytics.js', 'sources.sha'] as $file) {
            $this->assertFileExists(
                $this->publicPath($file),
                $file.' is missing from public/. Run `npm run build`.',
            );
        }
    }

    /**
     * The eight layers, all present and in this order.
     *
     * This is the contract that lets the kit's stylesheet, the package's and
     * the application's live together: the first declaration encountered fixes
     * the order for the whole document, and a stylesheet loaded later cannot
     * reorder what is already declared.
     *
     * The compiler is allowed to split the declaration — it lays down the
     * layers it fills, then names the rest — and to add its own, a `properties`
     * layer at the head. It reorders nothing, so what is read here is the
     * RELATIVE order of the eight, which is the contract, and not the raw list.
     */
    public function test_the_eight_layers_are_declared_in_the_agreed_order(): void
    {
        $contract = [
            'theme', 'base', 'ui-components', 'ui-utilities',
            'pkg-components', 'pkg-utilities', 'components', 'utilities',
        ];

        $this->assertSame(
            $contract,
            array_values(array_intersect($this->layersDeclaredIn('analytics.css'), $contract)),
            "The layer order of analytics.css is no longer the suite's.",
        );
    }

    /**
     * The stylesheet really carries the package's utilities.
     *
     * A stylesheet holding nothing but the layer line would pass both tests
     * above while drawing nothing — which is exactly the state the package was
     * in before its views carried their prefix.
     */
    public function test_the_sheet_carries_prefixed_utilities(): void
    {
        $css = (string) file_get_contents($this->publicPath('analytics.css'));

        $this->assertGreaterThan(
            200,
            substr_count($css, '.an\:'),
            'The stylesheet carries almost no prefixed rule: did the view scan find anything?',
        );

        $this->assertStringNotContainsString(
            'box-sizing',
            $css,
            'The reset belongs to the kit: two resets on one page fight each other.',
        );
    }

    /**
     * Has a source changed since the last build?
     *
     * This is what turns "remember to rebuild" into a test that fails.
     */
    public function test_the_fingerprint_matches_the_sources(): void
    {
        $this->assertSame(
            $this->fingerprintOfSources(),
            trim((string) file_get_contents($this->publicPath('sources.sha'))),
            'A source changed since the last build. Run `npm run build`, then commit `public/`.',
        );
    }

    /**
     * The layers in the order the document establishes them.
     *
     * A layer counts on its first appearance, whether it is opened with content
     * or only named in a list.
     *
     * @return list<string>
     */
    private function layersDeclaredIn(string $sheet): array
    {
        preg_match_all(
            '/@layer\s+([a-z0-9, -]+?)\s*[{;]/',
            (string) file_get_contents($this->publicPath($sheet)),
            $matches,
        );

        $seen = [];

        foreach ($matches[1] as $declaration) {
            foreach (explode(',', $declaration) as $layer) {
                $layer = trim($layer);

                if ($layer !== '' && ! in_array($layer, $seen, true)) {
                    $seen[] = $layer;
                }
            }
        }

        return $seen;
    }

    /**
     * The same calculation as `scripts/fingerprint.mjs`, and it has to stay so.
     *
     * Line endings are normalised, otherwise the fingerprint would differ
     * between a Windows machine and a Unix one for identical content.
     */
    private function fingerprintOfSources(): string
    {
        $root = $this->packagePath('');

        $named = [];

        foreach ($this->sourceFiles() as $file) {
            $named[str_replace('\\', '/', substr($file, strlen($root)))] = $file;
        }

        ksort($named, SORT_STRING);

        $hash = hash_init('sha256');

        foreach ($named as $name => $file) {
            hash_update($hash, $name);
            hash_update($hash, str_replace("\r\n", "\n", (string) file_get_contents($file)));
        }

        return hash_final($hash);
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $found = [];

        foreach ([['resources/js', '.js'], ['resources/css', '.css'], ['resources/views', '.blade.php']] as [$directory, $suffix]) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->packagePath($directory), FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    private function packagePath(string $path): string
    {
        return rtrim(dirname(__DIR__, 2).'/'.ltrim($path, '/'), '/').($path === '' ? '/' : '');
    }

    private function publicPath(string $file): string
    {
        return dirname(__DIR__, 2).'/public/'.$file;
    }
}
