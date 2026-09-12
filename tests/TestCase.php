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
use Falcon\Ui\UiServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->publishTheCompiledFiles();
    }

    /**
     * Publish the compiled files, when they are missing.
     *
     * **The kit refuses to build the address of a file the host has not
     * published**, and it is right to: an absent or stale copy would serve last
     * month's stylesheet without a word. The test application is a host like
     * any other, so it has to publish.
     *
     * It goes through the real command rather than a copy written here: a copy
     * would say nothing about the tag or the destination directory, and those
     * are precisely what we want held.
     *
     * **The condition is on the files, not on a process flag.** It therefore
     * recovers on its own if something removes the copy along the way; a flag
     * would leave every following test without a stylesheet, and their failure
     * would not speak of it.
     *
     * Before, the kit's files were only there because a `vendor:publish` run by
     * hand one day had left them. A `composer install` wiped them, and the
     * suite fell over an error that said nothing about itself.
     */
    private function publishTheCompiledFiles(): void
    {
        // A shipped file missing from this list would never be published: the
        // existing copy would be enough to conclude. It happened the day the
        // package gained its script.
        $expected = [
            public_path('vendor/falcon/ui/ui.css'),
            public_path('vendor/falcon/analytics/analytics.css'),
            public_path('vendor/falcon/analytics/analytics.js'),
        ];

        foreach ($expected as $file) {
            if (! is_file($file)) {
                $this->artisan('vendor:publish', ['--tag' => 'laravel-assets', '--force' => true])->run();

                return;
            }
        }
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
            UiServiceProvider::class,
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

        return $name.self::parallelSuffix();
    }

    /**
     * One database per parallel worker, or none when the run is sequential.
     *
     * Paratest names its workers in `TEST_TOKEN`. Without a database each, the
     * workers would migrate over one another and refresh each other's rows.
     */
    protected static function parallelSuffix(): string
    {
        $token = getenv('TEST_TOKEN');

        return is_string($token) && $token !== '' ? '_'.$token : '';
    }

    /**
     * Creates the worker's database when it is not there yet.
     *
     * Paratest hands out tokens, never databases, and asking the developer to
     * create eight by hand is a step that will be forgotten on the next machine.
     */
    protected static function ensureTheDatabaseExists(): void
    {
        if (self::parallelSuffix() === '') {
            return;
        }

        $connection = self::connectionForTests();

        $server = new PDO(
            sprintf('mysql:host=%s;port=%s', $connection['host'], $connection['port']),
            (string) $connection['username'],
            (string) $connection['password'],
        );

        $server->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            str_replace('`', '', (string) $connection['database']),
            $connection['charset'],
            $connection['collation'],
        ));
    }

    /**
     * An environment variable, or the fallback when it says nothing.
     *
     * `getenv` returns `false` when the variable is absent and `''` when it is
     * set empty: on a machine, both mean "I chose nothing". The password is the
     * exception and is genuinely the empty string, which the fallback gives it.
     */
    private static function fromEnvironment(string $name, string $fallback): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function connectionForTests(): array
    {
        return [
            'driver' => 'mysql',
            'host' => self::fromEnvironment('ANALYTICS_TEST_MYSQL_HOST', '127.0.0.1'),
            'port' => self::fromEnvironment('ANALYTICS_TEST_MYSQL_PORT', '3306'),
            'database' => self::databaseForTests(),
            'username' => self::fromEnvironment('ANALYTICS_TEST_MYSQL_USERNAME', 'root'),
            'password' => self::fromEnvironment('ANALYTICS_TEST_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,

            // InnoDB, said out loud: a local MySQL installation set to MyISAM
            // refuses the composite indexes this package lays on its events,
            // and the error comes out in the middle of a migration.
            'engine' => 'InnoDB',
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config): void {
            // MySQL, and nothing else. SQLite in memory held the suite in
            // twenty seconds and proved nothing of what the schema promises.
            //
            // **This package depends on that more than most**: its screens are
            // aggregations, so GROUP BY, date functions and composite indexes,
            // which is exactly where the two engines stop agreeing. A suite
            // green on one said nothing about the other, and the difference
            // came out in production.
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

            $config->set('analytics.admin.middleware', ['web', 'auth:admin']);
            $config->set('analytics.admin.layout', null);
        });
    }

    /**
     * Makes a table unreadable, for the length of one read.
     *
     * Several screens promise to degrade rather than return a 500 when a read
     * fails, and that is a promise only verifiable by genuinely breaking
     * something.
     *
     * **A rename, and not a `Schema::drop()`.** MySQL refuses to drop a table a
     * foreign key references, where SQLite allowed it: the drop failed, and the
     * test fell over its own tooling. The rename goes through, the constraints
     * follow, and queries on the old name fail exactly as wanted.
     *
     * **Put back whatever happens.** A DDL statement commits the transaction
     * `RefreshDatabase` was holding open: without the `finally`, the table
     * would stay absent for the whole rest of the suite, and dozens of tests
     * would fail for a reason that is not theirs.
     */
    protected function withoutTable(string $table, callable $read): void
    {
        Schema::rename($table, $table.'_absent');

        try {
            $read();
        } finally {
            Schema::rename($table.'_absent', $table);
        }
    }

    /**
     * Makes every query fail, without touching the schema.
     *
     * For what {@see self::withoutTable()} cannot cover: code that writes
     * **inside a transaction**. A DDL statement implicitly commits the
     * transaction `RefreshDatabase` holds open, so it takes the savepoints with
     * it, and the code under test fails on "SAVEPOINT trans2 does not exist"
     * instead of failing on its write. That is no longer the same thing being
     * measured.
     *
     * Shifting the table prefix on the **live** connection reconnects nothing:
     * the transaction stays open, so do its savepoints, and every query aims at
     * a table that does not exist. It is a real database error, at the right
     * moment, and there is nothing to put back but a prefix.
     */
    protected function withoutDatabase(callable $write): void
    {
        DB::connection()->setTablePrefix('absent_');

        try {
            $write();
        } finally {
            DB::connection()->setTablePrefix('');
        }
    }

    /**
     * Whether this run has already migrated. Static, so it spans the whole
     * process rather than one test.
     */
    private static bool $migratedThisRun = false;

    /**
     * Testbench calls this hook on every test, and `loadMigrationsFrom` makes
     * it migrate and roll back around each one: nineteen migrations up and
     * nineteen down, three hundred and sixty-seven times over. On MySQL every
     * schema statement is an implicit commit that reaches the disk, and that is
     * what the suite was paying for.
     *
     * The fixture path is handed to the migrator directly rather than through
     * `loadMigrationsFrom`, whose tear-down rollback is the half that costs.
     * Registered on every test all the same, so a `migrate:fresh` from
     * `RefreshDatabase` still finds it; only the run itself is guarded.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->app?->make('migrator')->path(__DIR__.'/Fixtures/migrations');

        if (self::$migratedThisRun) {
            return;
        }

        self::ensureTheDatabaseExists();

        $this->artisan('migrate')->run();

        self::$migratedThisRun = true;
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/login', fn (): string => 'login')->name('login');
        $router->get('/', fn (): string => 'home')->name('home');
        $router->get('/catalog', fn (): string => 'catalog')->name('catalog');
    }
}
