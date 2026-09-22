<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Les essais tournent sur l'environnement qu'on a choisi, pas sur celui qu'un
 * fichier égaré leur donne.
 *
 * **L'incident.** `vendor/bin/testbench`, qui lance le paquet dans une vraie
 * application et mérite d'être lancé, dépose son `.env.example` dans le
 * squelette du banc sous le nom `.env`. Ce fichier porte
 * `SESSION_DRIVER=cookie`, et il vaut pour TOUTE la suite. Quatre essais Search
 * Console sont alors tombés sur `Attempt to read property "cookies" on null` —
 * ils ouvrent une session hors d'une vraie requête, et une session « cookie »
 * a besoin d'une requête pour écrire son cookie. Rien à voir avec ce qu'ils
 * éprouvaient. Le fichier vit sous `vendor/`, donc `git stash` ne l'enlève pas,
 * ce qui le faisait passer pour un défaut du code.
 *
 * **Ce que cet essai remplace, et pourquoi.** Un essai précédent interdisait ce
 * fichier · il détectait la cause sans réparer quoi que ce soit, et laissait la
 * suite rouge jusqu'à ce qu'on l'efface à la main. Depuis que les variables
 * sont épinglées dans `phpunit.xml`, le fichier est **inoffensif** — mesuré le
 * 2026-09-14, avec lui en place : 530 essais sur 531 passent, le seul à tomber
 * étant ce détecteur devenu fausse alerte.
 *
 * **Un essai surveille la garantie, pas une des façons de la casser.** La
 * garantie, c'est que ces variables valent ce que nous avons décidé. Le fichier
 * égaré n'en était qu'une menace parmi d'autres.
 */
final class TheBenchRunsOnThePinnedEnvironmentTest extends TestCase
{
    /**
     * Ce qui est décidé par nous, et que rien d'autre ne doit décider.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pinned(): array
    {
        return [
            'le pilote de session' => ['SESSION_DRIVER', 'array', 'session.driver'],
            'le magasin de cache' => ['CACHE_STORE', 'array', 'cache.default'],
            'la file d’attente' => ['QUEUE_CONNECTION', 'sync', 'queue.default'],
            'la langue' => ['APP_LOCALE', 'fr', 'app.locale'],
        ];
    }

    /**
     * Ce que l'application dit vraiment, une fois démarrée.
     *
     * C'est le résultat qui compte · un réglage épinglé mais surchargé plus loin
     * ne servirait à rien, et c'est ici qu'on s'en apercevrait.
     */
    #[DataProvider('pinned')]
    public function test_the_running_application_uses_what_we_chose(string $variable, string $value, string $key): void
    {
        $this->assertSame(
            $value,
            config($key),
            "L’application tourne avec un autre {$key} que celui qu’on a épinglé. ".
            'Si un `.env` traîne dans le squelette du banc, c’est qu’il n’est plus neutralisé.',
        );
    }

    /**
     * Et l'épinglage est bien là, dans le fichier qui le pose.
     *
     * **C'est l'assertion qui discrimine.** Celle du dessus passerait aussi sans
     * épinglage, tant qu'aucun fichier égaré ne traîne — donc elle ne dit rien
     * du jour où il en traînera un. Celle-ci tombe dès que la ligne disparaît de
     * `phpunit.xml`, c'est-à-dire au moment où la protection part, et non des
     * semaines plus tard sur un essai qui parle d'autre chose.
     */
    #[DataProvider('pinned')]
    public function test_the_configuration_pins_it_before_anything_else_can(string $variable, string $value, string $key): void
    {
        $declaration = (string) file_get_contents(dirname(__DIR__, 2).'/phpunit.xml');

        $this->assertStringContainsString(
            sprintf('<env name="%s" value="%s"/>', $variable, $value),
            $declaration,
            "phpunit.xml n’épingle plus {$variable}. Ce qui y est déclaré est posé AVANT que ".
            'l’application démarre, et le chargeur du cadre ne remplace jamais une variable déjà '.
            'posée · sans cette ligne, un `.env` déposé dans le squelette du banc décide à sa place, '.
            'pour toute la suite, et les essais qui tombent ne parlent pas de lui.',
        );
    }
}
