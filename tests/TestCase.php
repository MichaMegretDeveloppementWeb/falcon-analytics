<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Dotenv\Dotenv;
use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\Fixtures\Models\TestLessor;
use Falcon\UiKit\UiKitServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

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

    private static bool $envLoaded = false;

    /**
     * Reads `.env.test` at the package root, when there is one.
     *
     * Kept out of the repository: it carries a database password. The committed
     * `.env.test.example` says what to put in it. Variables already present in
     * the environment win, so a CI run can set them without a file.
     */
    protected static function loadTestEnvironment(): void
    {
        if (self::$envLoaded) {
            return;
        }

        self::$envLoaded = true;

        $root = dirname(__DIR__);

        if (is_file($root.'/.env.test')) {
            Dotenv::createUnsafeImmutable($root, '.env.test')->safeLoad();
        }
    }

    /**
     * The database every test runs against, named by `.env.test` alone.
     *
     * Never derived from the host's own configuration, and never guessed. The
     * suite migrates fresh, which drops every table it finds, so a wrong name
     * here does not fail a test: it destroys a working site.
     *
     * The name must end in `_test`. That is the whole safety net, and it is
     * deliberately mechanical: no amount of care about which value went into
     * which file protects as well as a rule the code refuses to break.
     *
     * @throws RuntimeException
     */
    protected static function databaseForTests(): string
    {
        self::loadTestEnvironment();

        $name = getenv('ANALYTICS_TEST_MYSQL_DATABASE');

        if (! is_string($name) || $name === '') {
            throw new RuntimeException(
                'No test database: set ANALYTICS_TEST_MYSQL_DATABASE in .env.test '
                .'(see .env.test.example). The suite never guesses a database.'
            );
        }

        if (! str_ends_with($name, '_test')) {
            throw new RuntimeException(
                "The test database '{$name}' does not end in '_test'. Refused: "
                .'the suite migrates from scratch and would erase whatever it finds.'
            );
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function connectionForTests(): array
    {
        return [
            'driver' => 'mysql',
            'host' => getenv('ANALYTICS_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('ANALYTICS_TEST_MYSQL_PORT') ?: '3306',
            'database' => self::databaseForTests(),
            'username' => getenv('ANALYTICS_TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('ANALYTICS_TEST_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,

            // InnoDB, dit à voix haute · une installation MySQL locale réglée
            // sur MyISAM refuse les index composites que ce paquet pose sur ses
            // événements, et l'erreur sort au milieu d'une migration.
            'engine' => 'InnoDB',
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config): void {
            // MySQL, et rien d'autre. SQLite en mémoire tenait la suite en
            // vingt secondes et ne prouvait rien de ce que le schéma promet.
            //
            // **Ce paquet en dépend plus que la plupart** · ses écrans sont des
            // agrégations, donc du GROUP BY, des fonctions de date et des index
            // composites, et c'est exactement là que les deux moteurs cessent
            // d'être d'accord. Une suite verte sur l'un ne disait rien de
            // l'autre, et la différence sortait en production.
            $config->set('database.connections.mysql_testing', self::connectionForTests());
            $config->set('database.default', 'mysql_testing');

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

    /**
     * Rend une table illisible, le temps d'une lecture.
     *
     * Plusieurs écrans promettent de se dégrader plutôt que de rendre une 500
     * quand une lecture échoue, et c'est une promesse qui ne se vérifie qu'en
     * cassant vraiment quelque chose.
     *
     * **Un renommage, et non un `Schema::drop()`.** MySQL refuse de supprimer
     * une table qu'une clé étrangère référence, là où SQLite laissait faire :
     * la suppression échouait, et l'essai tombait sur son propre outil. Le
     * renommage passe, les contraintes suivent, et les requêtes sur l'ancien
     * nom échouent exactement comme on le veut.
     *
     * **Remise en place dans tous les cas.** Une instruction DDL valide la
     * transaction que `RefreshDatabase` tenait ouverte : sans le `finally`, la
     * table resterait absente pour tout le reste de la suite, et des dizaines
     * d'essais tomberaient pour une raison qui n'est pas la leur.
     */
    protected function withoutTable(string $table, callable $read): void
    {
        Schema::rename($table, $table.'_absente');

        try {
            $read();
        } finally {
            Schema::rename($table.'_absente', $table);
        }
    }

    /**
     * Fait échouer toute requête, sans toucher au schéma.
     *
     * Pour ce que {@see self::withoutTable()} ne peut pas couvrir : un code qui
     * écrit **dans une transaction**. Une instruction DDL valide implicitement
     * la transaction que `RefreshDatabase` tient ouverte, donc elle emporte les
     * points de reprise avec elle, et le code sous test échoue sur
     * « SAVEPOINT trans2 does not exist » au lieu d'échouer sur son écriture.
     * Ce n'est plus la même chose qu'on mesure.
     *
     * Décaler le préfixe de tables sur la connexion **vivante** ne reconnecte
     * rien : la transaction reste ouverte, ses points de reprise aussi, et
     * chaque requête vise une table qui n'existe pas. C'est une vraie erreur de
     * base, au bon moment, et il n'y a rien à remettre en place qu'un préfixe.
     */
    protected function withoutDatabase(callable $write): void
    {
        DB::connection()->setTablePrefix('absente_');

        try {
            $write();
        } finally {
            DB::connection()->setTablePrefix('');
        }
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
