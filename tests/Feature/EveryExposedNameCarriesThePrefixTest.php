<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every name the package exposes to the cascade or to scripts carries its prefix.
 *
 * A utility prefix isolates utilities only: `an:flex` protects neither a
 * hand-written class, nor an animation, nor a data attribute. Those share a
 * namespace with the host and the other packages of the suite, where two
 * identical names overwrite each other silently. An attribute costs most: a
 * screen draws inside the host's layout, so a listener on the window also
 * catches the host's elements.
 *
 * `EveryClassCarriesThePrefixTest` guards unprefixed Tailwind utilities; the
 * two do not overlap.
 */
final class EveryExposedNameCarriesThePrefixTest extends TestCase
{
    private const PREFIX = 'an';

    /**
     * What the package may write without its prefix: what does not belong to it.
     *
     * @var list<string>
     */
    private const SHARED = [
        // The kit's, which it reads itself.
        'data-ui-scope',
        'data-ui-area',

        // The framework's and its libraries'.
        'data-navigate-track',
        'data-csrf',
        'data-module-url',
        'data-update-uri',
        'data-navigate-once',
    ];

    /** @return list<SplFileInfo> */
    private static function views(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * The three families, and the pattern that finds each.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function exposedNames(): array
    {
        return [
            'un attribut de données' => ['/\b(data-[a-z][a-z0-9-]*)(?:\s*=|[\'"]\s*=>)/', 'data-'.self::PREFIX.'-…'],
            'une animation' => ['/@keyframes\s+([a-z][a-z0-9-]*)/', self::PREFIX.'-…'],
            'une classe écrite à la main' => ['/\.((?!'.self::PREFIX.'-)[a-z][a-z0-9]*-[a-z0-9-]+)\s*\{/', self::PREFIX.'-…'],
        ];
    }

    #[DataProvider('exposedNames')]
    public function test_no_view_exposes_an_unprefixed_name(string $pattern, string $shape): void
    {
        $offenders = [];

        foreach (self::views() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $name) {
                if (in_array($name, self::SHARED, true) || str_starts_with($name, self::PREFIX.'-')) {
                    continue;
                }

                if (str_starts_with($name, 'data-'.self::PREFIX.'-')) {
                    continue;
                }

                $offenders[] = $file->getFilename().' · '.$name;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'Ces noms sortent du paquet sans son préfixe, et rien ne les isole de l’hôte ni des '.
            "autres paquets. Renommez-les en « {$shape} », ou ajoutez-les à SHARED s’ils ".
            'appartiennent au kit ou au cadre.',
        );
    }
}
