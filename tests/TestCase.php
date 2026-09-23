<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests;

use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;
use Dotenv\Dotenv;
use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\Fixtures\Models\TestLessor;
use Falcon\Ui\UiServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use RuntimeException;
use Throwable;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        self::isolateTheBootstrapCache(self::parallelToken());

        parent::setUp();

        $this->withoutVite();
        $this->publishTheCompiledFiles();

        // Lazy loading lives in a static that survives between tests until a state flush.
        Livewire::flushState();

        // Eloquent runs strict, as it does outside production.
        Model::shouldBeStrict();
    }

    /**
     * One provider manifest and one compiled-view directory per parallel worker.
     *
     * Workers share the bench application, and each of these files is written
     * through a `rename()`, which Windows refuses onto a file another process
     * is reading. A sequential run keeps the default paths.
     *
     * Set through the three channels `Env` reads, before the application boots:
     * the paths are resolved while providers are registered.
     */
    protected static function isolateTheBootstrapCache(string $token): void
    {
        $paths = [
            'APP_SERVICES_CACHE' => $token === '' ? null : "bootstrap/cache/services-{$token}.php",
            'APP_PACKAGES_CACHE' => $token === '' ? null : "bootstrap/cache/packages-{$token}.php",

        ];

        // Absolute and created here: Blade writes into this directory without ever creating it.
        if ($token !== '') {
            $compiled = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel/storage/framework/views-'.$token;

            if (! is_dir($compiled)) {
                mkdir($compiled, 0o777, true);
            }

            $paths['VIEW_COMPILED_PATH'] = $compiled;
        } else {
            $paths['VIEW_COMPILED_PATH'] = null;
        }

        foreach ($paths as $key => $path) {
            if ($path === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv("{$key}={$path}");
            $_ENV[$key] = $path;
            $_SERVER[$key] = $path;
        }
    }

    /** Paratest's name for this worker, or an empty string when the run is sequential. */
    private static function parallelToken(): string
    {
        $token = getenv('TEST_TOKEN');

        return is_string($token) ? $token : '';
    }

    /**
     * Where a worker publishes the compiled files.
     *
     * One public directory per parallel worker, so no worker renders a page
     * while another is still copying a file into it. The copies stay after the
     * run, so the next one publishes nothing.
     */
    private static function publishedDirectoryFor(string $token): string
    {
        $base = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel/public';

        return $token === '' ? $base : $base.'-'.$token;
    }

    /**
     * Publish the compiled files whenever a copy differs from the shipped one.
     *
     * The kit refuses to serve a missing or stale copy, and compares the bytes
     * as this does. The real command publishes, so its tag and destination are
     * exercised too. The check reads the files on every test, so a copy removed
     * along the way is published again.
     */
    private function publishTheCompiledFiles(): void
    {
        // Read from the directories, so a newly shipped file is compared too.
        $shippedBy = [
            'ui' => dirname(__DIR__).'/vendor/falcon/ui-kit/public',
            'analytics' => dirname(__DIR__).'/public',
        ];

        foreach ($shippedBy as $package => $directory) {
            $files = glob($directory.'/*');

            foreach ($files === false ? [] : $files as $source) {
                $published = public_path('vendor/falcon/'.$package.'/'.basename($source));

                if (is_file($published) && filesize($published) === filesize($source)
                    && md5_file($published) === md5_file($source)) {
                    continue;
                }

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
        // The bench discovers no provider, and Carbon's is what localises dates.
        return [
            CarbonServiceProvider::class,
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
     * The name must end in `_test`: a mechanical check, which holds whichever
     * file the value came from.
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

    /** Whether this process has already asked for its database and its removal. */
    private static bool $workerDatabaseClaimed = false;

    /**
     * Creates the worker's database when it is not there yet, and drops it when
     * this process ends.
     *
     * Paratest hands out tokens, never databases. Leftover worker databases
     * overflow MySQL's table definition cache, which then answers « 1615
     * Prepared statement needs to be re-prepared » to a valid query.
     */
    protected static function ensureTheDatabaseExists(): void
    {
        if (self::parallelSuffix() === '' || self::$workerDatabaseClaimed) {
            return;
        }

        self::$workerDatabaseClaimed = true;

        $connection = self::connectionForTests();
        $name = str_replace('`', '', (string) $connection['database']);

        self::server()->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            $name,
            $connection['charset'],
            $connection['collation'],
        ));

        // Fires once the worker's whole share is done, not after each class.
        register_shutdown_function(static function () use ($name): void {
            try {
                self::server()->exec("DROP DATABASE IF EXISTS `{$name}`");
            } catch (Throwable) {
                // Housekeeping never fails the suite; the next run with this token drops it.
            }
        });
    }

    /** A connection to the server itself, with no database selected. */
    private static function server(): PDO
    {
        $connection = self::connectionForTests();

        return new PDO(
            sprintf('mysql:host=%s;port=%s', $connection['host'], $connection['port']),
            (string) $connection['username'],
            (string) $connection['password'],
        );
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

            // A MyISAM default refuses the composite indexes on the events tables.
            'engine' => 'InnoDB',
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->usePublicPath(self::publishedDirectoryFor(self::parallelToken()));

        tap($app['config'], function ($config): void {
            // MySQL only: the screens aggregate with GROUP BY, date functions and composite indexes.
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
            $config->set('analytics.layouts.admin', null);
        });
    }

    /**
     * The package's schema as the engine holds it: one line per column, per
     * index, per foreign key and per check constraint, sorted so two readings
     * compare.
     *
     * Scoped to the connection's own database — `information_schema` holds
     * every database of the server, and an unscoped listing would read the
     * tables of every other project on it.
     *
     * @return list<string>
     */
    protected function schemaOfThePackage(): array
    {
        $columns = DB::table('information_schema.COLUMNS')
            ->selectRaw("CONCAT('COL ', TABLE_NAME, '.', COLUMN_NAME, ' ', COLUMN_TYPE, ' null=', IS_NULLABLE, ' def=', IFNULL(COLUMN_DEFAULT, '-'), ' extra=', EXTRA) AS line")
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->orderBy('TABLE_NAME')
            ->orderBy('COLUMN_NAME')
            ->pluck('line');

        $indexes = DB::table('information_schema.STATISTICS')
            ->selectRaw("CONCAT('IDX ', TABLE_NAME, '.', INDEX_NAME, ' unique=', IF(NON_UNIQUE = 0, 'oui', 'non'), ' (', GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX), ')') AS line")
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->groupBy('TABLE_NAME', 'INDEX_NAME', 'NON_UNIQUE')
            ->orderBy('TABLE_NAME')
            ->orderBy('INDEX_NAME')
            ->pluck('line');

        // Foreign keys with their delete rule: no column or index reveals a lost cascade.
        $keys = DB::table('information_schema.KEY_COLUMN_USAGE AS kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS AS rc', function (JoinClause $join): void {
                $join->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA')
                    ->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME');
            })
            ->selectRaw("CONCAT('FK ', kcu.TABLE_NAME, '.', kcu.CONSTRAINT_NAME, ' (', kcu.COLUMN_NAME, ') -> ', kcu.REFERENCED_TABLE_NAME, '.', kcu.REFERENCED_COLUMN_NAME, ' del=', rc.DELETE_RULE, ' upd=', rc.UPDATE_RULE) AS line")
            ->whereRaw('kcu.TABLE_SCHEMA = DATABASE()')
            ->where('kcu.TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->whereNotNull('kcu.REFERENCED_TABLE_NAME')
            ->orderBy('kcu.TABLE_NAME')
            ->orderBy('kcu.CONSTRAINT_NAME')
            ->pluck('line');

        // Check constraints by name: losing one changes no column and no index.
        $checks = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->selectRaw("CONCAT('CHK ', TABLE_NAME, '.', CONSTRAINT_NAME) AS line")
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->orderBy('TABLE_NAME')
            ->orderBy('CONSTRAINT_NAME')
            ->pluck('line');

        return array_values($columns->merge($indexes)->merge($keys)->merge($checks)
            ->map(fn (mixed $line): string => (string) $line)
            ->all());
    }

    /**
     * Makes a table unreadable for the length of one read, to prove a screen
     * degrades instead of returning a 500.
     *
     * A rename, since MySQL refuses to drop a table a foreign key references.
     * Put back in a `finally`: the DDL commits the `RefreshDatabase` transaction.
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
     * What an operation costs in package statements, and how many it repeats.
     *
     * Only the package's own tables are counted: the bench opens a transaction
     * and reads the framework's tables around every test, and none of that
     * belongs to the plan being measured.
     *
     * @param  callable(): mixed  $run
     * @return array{count: int, duplicates: int}
     */
    protected function statementsFor(callable $run): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $run();

        $signatures = Collection::make(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'falcon_analytics_'))
            ->map(fn (array $query): string => $query['query'].'|'.json_encode($query['bindings']));

        DB::disableQueryLog();

        return [
            'count' => $signatures->count(),
            'duplicates' => $signatures->count() - $signatures->unique()->count(),
        ];
    }

    /**
     * The same cost at two volumes: a plan is fixed, or it is not a plan.
     *
     * A ceiling measured on one row says nothing of what happens at a hundred:
     * a budget of twenty still passes at one statement per five rows. This
     * seeds once, measures, seeds `$extra` more, and measures again — the
     * database is not reset between the two, so the second pass really does see
     * the whole volume.
     *
     * Returns the cost at the larger volume, so a caller can still assert its
     * own ceiling on top.
     *
     * @param  callable(): mixed  $seedOne
     * @param  callable(): mixed  $run
     * @return array{count: int, duplicates: int}
     */
    protected function assertCostIsFlat(callable $seedOne, callable $run, int $extra = 30): array
    {
        $seedOne();
        $small = $this->statementsFor($run);

        foreach (range(1, $extra) as $ignored) {
            $seedOne();
        }

        $large = $this->statementsFor($run);

        $this->assertSame(
            $small['count'],
            $large['count'],
            sprintf('The plan is not fixed: %d statements at 1 row, %d at %d.', $small['count'], $large['count'], $extra + 1),
        );

        $this->assertSame(0, $large['duplicates'], 'A read repeats itself at volume.');

        return $large;
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
     * process, not one test.
     */
    private static bool $migratedThisRun = false;

    /**
     * Migrates once per process: `loadMigrationsFrom` would migrate up and down
     * around every test, and each schema statement is a commit on MySQL.
     *
     * The fixture path is registered on every test, so a `migrate:fresh` from
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
