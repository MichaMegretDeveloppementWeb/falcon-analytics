<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Application;

/**
 * Each test process has its own bootstrap cache.
 *
 * Paratest workers boot the same bench application, whose provider and package
 * manifests are one file each, rewritten through a `rename()` that Windows
 * refuses onto a file another process reads. The framework takes both paths
 * from the environment, and paratest names each worker in `TEST_TOKEN`.
 */
final class EachWorkerKeepsItsOwnBootstrapCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore the run's own setting: the worker's token under paratest, none otherwise.
        $token = getenv('TEST_TOKEN');
        self::isolateTheBootstrapCache(is_string($token) ? $token : '');

        parent::tearDown();
    }

    /** A fresh `Application` is where the framework reads both paths from the environment. */
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

    public function test_a_sequential_run_keeps_the_default_paths(): void
    {
        self::isolateTheBootstrapCache('');

        $application = new Application(dirname(__DIR__, 2).'/vendor/orchestra/testbench-core/laravel');

        $this->assertStringEndsWith('cache/services.php', str_replace('\\', '/', $application->getCachedServicesPath()));
        $this->assertStringEndsWith('cache/packages.php', str_replace('\\', '/', $application->getCachedPackagesPath()));
    }
}
