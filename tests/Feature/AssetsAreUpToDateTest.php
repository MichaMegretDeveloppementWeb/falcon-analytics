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
 * An application serves the compiled files without rebuilding them, so a stale
 * file draws bad screens silently. The views count as much as the CSS, since
 * the utility generator reads them. The fingerprint comes from
 * `scripts/fingerprint.mjs`; whether a build is reproducible is the job of
 * `npm run check-assets`.
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
     * The first declaration fixes the layer order for the whole document. The
     * compiler may split it or add a `properties` layer, so the relative order
     * of the eight is what is read.
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

    /** A sheet holding only the layer line would pass the tests above while drawing nothing. */
    public function test_the_sheet_carries_prefixed_utilities(): void
    {
        $css = (string) file_get_contents($this->publicPath('analytics.css'));

        $this->assertGreaterThan(
            200,
            substr_count($css, '.an\:'),
            'The stylesheet carries almost no prefixed rule: did the view scan find anything?',
        );

        // Read as a layer, since scoped third-party rules may set `box-sizing` without being a reset.
        $this->assertStringNotContainsString(
            '@layer base{',
            $css,
            'The sheet opens the base layer, which is the kit\'s: it would ship a second reset.',
        );
    }

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

        foreach ([['resources/js', '.js'], ['resources/css', '.css'], ['resources/views', '.blade.php'], ['resources/svg', '.svg']] as [$directory, $suffix]) {
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
