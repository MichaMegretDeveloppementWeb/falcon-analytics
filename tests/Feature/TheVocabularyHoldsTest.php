<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * One word per notion, as `docs/fonctionnalites.md` writes it under « Le
 * vocabulaire ». Reads the strings handed to `__()`, and every string of what
 * the package prints in a terminal, never the comments, and refuses every word
 * that section retires.
 */
final class TheVocabularyHoldsTest extends TestCase
{
    /**
     * Where every string is read: the commands, the summaries of the seeders,
     * and the names the test data lays on screens. They do not go through
     * `__()`, and an integrator reads them all the same.
     */
    private const PRINTED = ['src/Console', 'database/seeders', 'database/factories'];

    /** Retired word => the word to write instead. */
    private const RETIRED = [
        '/\bpubs?\b/iu' => 'publicité',
        '/évènement/iu' => 'événement',
        '/\btop\b/iu' => '« … les plus vues » ou « … principaux »',
        '/^Analytics$/u' => 'Audience',
        '/\bvisites?\b/iu' => 'session',
        '/tagg|tagu/iu' => '« arrivée par un lien qui porte des paramètres »',
        '/\b(tu|tes|ton|ta|définis)\b/iu' => 'le vouvoiement',
        '/\.\.\./u' => '« … »',
        '/—/u' => '« · », « : » ou une virgule',
        '/\bpts\b/u' => 'NumberLabel::points()',
        '/Données indisponibles/u' => '« Impossible de charger … », et ce qui manque',
        '/non trouvée|erreur 404/iu' => '« Cette page n’existe pas ou plus. »',
    ];

    public function test_no_visible_string_uses_a_retired_word(): void
    {
        $offences = [];

        foreach ($this->visibleStrings() as [$where, $string]) {
            foreach (self::RETIRED as $pattern => $instead) {
                if (preg_match($pattern, $string, $match) === 1) {
                    $offences[] = "{$where} · « {$match[0]} » in « {$string} » · write {$instead}";
                }
            }
        }

        $this->assertSame([], $offences);
    }

    /** @return list<array{string, string}> */
    private function visibleStrings(): array
    {
        $root = dirname(__DIR__, 2);
        $strings = [];

        $files = Finder::create()->files()->name('*.php')->in([$root.'/src', $root.'/resources/views']);

        foreach ($files as $file) {
            preg_match_all('/__\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/s', $file->getContents(), $found);

            foreach ($found[1] as $literal) {
                $string = stripslashes(substr($literal, 1, -1));

                if ($string !== 'pt' && $string !== 'pts') {
                    $strings[] = [$file->getRelativePathname(), $string];
                }
            }
        }

        $printed = Finder::create()->files()->name('*.php')->in(array_map(
            static fn (string $directory): string => $root.'/'.$directory,
            self::PRINTED,
        ));

        foreach ($printed as $file) {
            $where = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            foreach (token_get_all($file->getContents()) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $strings[] = [$where, stripslashes(substr($token[1], 1, -1))];
                }

                if (is_array($token) && $token[0] === T_ENCAPSED_AND_WHITESPACE) {
                    $strings[] = [$where, $token[1]];
                }
            }
        }

        return $strings;
    }
}
