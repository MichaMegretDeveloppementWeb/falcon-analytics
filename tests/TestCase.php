<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\Fixtures\Models\TestLessor;
use Falcon\UiKit\UiKitServiceProvider;
use Illuminate\Routing\Router;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            UiKitServiceProvider::class,
            AnalyticsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config): void {
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);

            $config->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
            $config->set('auth.guards.client', ['driver' => 'session', 'provider' => 'clients']);
            $config->set('auth.guards.lessor', ['driver' => 'session', 'provider' => 'lessors']);
            $config->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => TestAdmin::class]);
            $config->set('auth.providers.clients', ['driver' => 'eloquent', 'model' => TestClient::class]);
            $config->set('auth.providers.lessors', ['driver' => 'eloquent', 'model' => TestLessor::class]);

            $config->set('analytics.identity.subject_guards', ['client', 'lessor']);
            $config->set('analytics.identity.exclude_guards', ['admin']);
            $config->set('analytics.identity.subjects', [
                'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
                'lessor' => ['label' => 'Loueur', 'name' => ['first_name', 'last_name']],
            ]);

            $config->set('analytics.dashboard.middleware', ['web', 'auth:admin']);
            $config->set('analytics.dashboard.layout', null);
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/login', fn (): string => 'login')->name('login');
        $router->get('/', fn (): string => 'home')->name('home');
        $router->get('/catalog', fn (): string => 'catalog')->name('catalog');
    }
}
