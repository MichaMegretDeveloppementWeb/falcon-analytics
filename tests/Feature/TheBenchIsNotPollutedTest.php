<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Nothing has left an environment file in the bench's skeleton.
 *
 * **This exists because of an afternoon.** Running the package in a real
 * application — `vendor/bin/testbench …`, which is worth doing and is written
 * up in `docs/developpement.md` — makes testbench copy its `.env.example` into
 * the skeleton as `.env`. That file then applies to the WHOLE suite, and it
 * carries `SESSION_DRIVER=cookie` where testbench's own configuration defaults
 * to `array`.
 *
 * The consequence has nothing to do with what one was testing · four Search
 * Console essays fell over `Attempt to read property "cookies" on null`,
 * because `withSession()` outside a request cannot write a cookie session. The
 * file is under `vendor/`, so it survives a `git stash` — which is what made it
 * look, convincingly, like a defect in the code rather than in the bench.
 *
 * **Measured 2026-09-13**, and a serious candidate for the intermittent failure
 * this suite had been showing for weeks without an explanation.
 *
 * So the bench says it out loud now. A suite-wide breakage becomes one named
 * essay with the remedy in its message.
 */
final class TheBenchIsNotPollutedTest extends TestCase
{
    public function test_the_skeleton_carries_no_environment_file_of_its_own(): void
    {
        $stray = dirname(__DIR__, 2).'/vendor/orchestra/testbench-core/laravel/.env';

        $this->assertFileDoesNotExist($stray, <<<'TEXT'

            Le squelette du banc porte un fichier .env, et il change
            l'environnement de TOUTE la suite — notamment le pilote de session,
            qui passe en « cookie » et fait tomber des essais qui n'ont rien à
            voir.

            Il est déposé par `vendor/bin/testbench`, qui sert à lancer le
            paquet dans une vraie application. Ce n'est pas une faute : c'est un
            effet de bord à connaître.

            Remède · supprimez-le.
              rm vendor/orchestra/testbench-core/laravel/.env

            TEXT);
    }
}
