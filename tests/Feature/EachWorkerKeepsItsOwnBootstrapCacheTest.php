<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Application;

/**
 * Chaque processus d'essai a son propre cache de démarrage.
 *
 * **L'échec intermittent de la suite, enfin pris sur le fait le 2026-09-14.**
 * Les quatre processus de paratest démarrent la même application d'essai, dont
 * les manifestes `bootstrap/cache/services.php` et `packages.php` sont UN seul
 * fichier chacun. Quand un manifeste est périmé — un `vendor/bin/testbench`
 * l'a écrit avec d'autres fournisseurs, un `composer update` a changé la liste
 * — chaque processus le réécrit au démarrage, par un fichier temporaire et un
 * `rename()`. Sous Windows, renommer par-dessus un fichier qu'un autre
 * processus lit est refusé · un essai sur quatre cents tombait dans son
 * `setUp` avec « Accès refusé (code: 5) », sur rien de ce qu'il éprouvait.
 * Une fois tous les processus d'accord avec le fichier, dix passages verts
 * suivaient — ce qui lui donnait l'air du hasard.
 *
 * Le cadre laisse nommer ces deux chemins par l'environnement, et paratest
 * nomme ses processus dans `TEST_TOKEN` · chacun reçoit donc sa paire.
 */
final class EachWorkerKeepsItsOwnBootstrapCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        // Put the environment back to what the run itself decided · the real
        // worker's token under paratest, nothing at all otherwise.
        $token = getenv('TEST_TOKEN');
        self::isolateTheBootstrapCache(is_string($token) ? $token : '');

        parent::tearDown();
    }

    /**
     * Asked of a bare application rather than of the booted one · what is
     * held is that the framework resolves the two paths from what the bench
     * put in the environment, and a fresh `Application` is where that reading
     * happens, without a database behind it.
     */
    public function test_a_named_worker_gets_its_own_manifests(): void
    {
        self::isolateTheBootstrapCache('essai');

        $application = new Application(dirname(__DIR__, 2).'/vendor/orchestra/testbench-core/laravel');

        $this->assertStringEndsWith(
            'services-essai.php',
            str_replace('\\', '/', $application->getCachedServicesPath()),
            'Le manifeste des fournisseurs doit porter le nom du processus, sinon quatre processus se le réécrivent.',
        );

        $this->assertStringEndsWith(
            'packages-essai.php',
            str_replace('\\', '/', $application->getCachedPackagesPath()),
            'Celui des paquets aussi · il est écrit de la même façon, au même moment.',
        );
    }

    /** And a sequential run keeps the ordinary paths, so nothing piles up for nothing. */
    public function test_a_sequential_run_keeps_the_default_paths(): void
    {
        self::isolateTheBootstrapCache('');

        $application = new Application(dirname(__DIR__, 2).'/vendor/orchestra/testbench-core/laravel');

        $this->assertStringEndsWith('cache/services.php', str_replace('\\', '/', $application->getCachedServicesPath()));
        $this->assertStringEndsWith('cache/packages.php', str_replace('\\', '/', $application->getCachedPackagesPath()));
    }
}
