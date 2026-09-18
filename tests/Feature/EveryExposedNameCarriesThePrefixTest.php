<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Tout nom que le paquet expose à la cascade ou au script porte son préfixe.
 *
 * Un préfixe d'utilitaires n'isole que les utilitaires · `an:flex` ne protège
 * ni une classe écrite à la main, ni une animation, ni un attribut de données.
 * Ces trois familles vivent dans un espace de noms **partagé avec l'hôte et
 * avec les autres paquets de la suite**, et deux noms identiques s'y écrasent
 * sans que rien ne le dise.
 *
 * Le cas le plus coûteux est l'attribut · un écran du paquet se dessine dans le
 * layout de l'hôte, et un écouteur posé sur la fenêtre accroche alors aussi les
 * éléments de l'hôte. Un nom aussi répandu que `data-tooltip` se réclame de
 * deux propriétaires à la fois.
 *
 * `EveryClassCarriesThePrefix` garde une autre chose · les utilitaires Tailwind
 * qu'on aurait écrits sans leur préfixe. Les deux ne se recouvrent pas.
 */
final class EveryExposedNameCarriesThePrefixTest extends TestCase
{
    private const PREFIX = 'an';

    /**
     * Ce que le paquet a le droit d'écrire sans son préfixe · ce qui ne lui
     * appartient pas.
     *
     * @var list<string>
     */
    private const SHARED = [
        // Ceux du kit, qu'il lit lui-même.
        'data-ui-scope',
        'data-ui-area',

        // Ceux du cadre et de ses bibliothèques.
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
     * Les trois familles, et l'expression qui les trouve.
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
