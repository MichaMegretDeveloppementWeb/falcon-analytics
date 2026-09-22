<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests;

use Dotenv\Dotenv;
use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\Fixtures\Models\TestLessor;
use Falcon\Ui\UiServiceProvider;
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

        // Lazy loading is switched off by a static that only a state flush
        // clears. A test that asked for it therefore hands it to the next ones
        // in the same process, and the first render of each is the one that
        // inherits it.
        Livewire::flushState();
    }

    /**
     * One provider manifest per parallel worker, instead of one for all.
     *
     * **This is the intermittent failure, and it took a run to catch it in
     * the act.** Every worker boots the same test application, whose
     * `bootstrap/cache/services.php` and `packages.php` are ONE file each. When
     * a manifest is stale — a `vendor/bin/testbench` run wrote it with another
     * set of providers, or a `composer update` changed the list — every worker
     * rewrites it at boot, through a temporary file and a `rename()`. On
     * Windows a rename onto a file another process is reading is refused, and
     * one test in four hundred fell with « Accès refusé (code: 5) » in its
     * `setUp`, about nothing it was testing. Once every worker agreed with the
     * file, the next ten runs were green, which is exactly what made it look
     * like chance. Measured 2026-09-14.
     *
     * The framework lets the two paths be named through the environment, and
     * paratest names its workers in `TEST_TOKEN` · so each worker gets its own
     * pair, and nobody renames over anybody. A sequential run keeps the
     * default paths.
     *
     * Set through the three channels the framework's `Env` may read, before
     * the application boots · the paths are resolved while providers are
     * being registered, and there is no later.
     */
    protected static function isolateTheBootstrapCache(string $token): void
    {
        $paths = [
            'APP_SERVICES_CACHE' => $token === '' ? null : "bootstrap/cache/services-{$token}.php",
            'APP_PACKAGES_CACHE' => $token === '' ? null : "bootstrap/cache/packages-{$token}.php",

        ];

        /*
         * The compiled views, for the same reason — and it took a second
         * incident to see it. They live in ONE directory too, and Blade writes
         * each one through a temporary file and a `rename()`.
         *
         * It stayed hidden because the directory is normally already full:
         * nobody writes, so nobody collides. Empty it — which is what one does
         * after changing a component's constructor — and all four workers
         * compile the same views at the same instant. Three essays fell on
         * « Accès refusé (code: 5) », about nothing they were testing.
         * Measured 2026-09-14, right after the bootstrap-cache fix that shares
         * this reasoning.
         *
         * **An absolute path, and the directory created here.** The framework's
         * default is absolute, and Blade writes into this directory without
         * ever creating it — a relative name would resolve against the working
         * directory, and a missing one fails on the first view.
         */
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
     * **The third of the three things a parallel run has to give each worker**,
     * next to its database and its bootstrap cache above — and the one that was
     * missed. Every worker boots the same test application, so `public_path()`
     * is ONE directory for the four of them.
     *
     * On a workstation the copies are already in place, nobody publishes, and
     * nothing collides. On a fresh checkout the directory is empty: the four
     * workers publish at once, and one of them renders a page while another is
     * still copying `icons.svg`. The kit's staleness guard reads half a file,
     * refuses to build the address, and the test that falls is about something
     * else entirely — `test_it_renders_a_session_detail…`, which touches none
     * of this.
     *
     * **Measured on the integration chain** · green, red, green on the same
     * code, only on the line that resolves the floor. That is what an unshared
     * directory looks like from the outside: chance.
     *
     * The copies are kept rather than dropped at the end: they are what makes
     * the next run publish nothing at all.
     */
    private static function publishedDirectoryFor(string $token): string
    {
        $base = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel/public';

        return $token === '' ? $base : $base.'-'.$token;
    }

    /**
     * Publish the compiled files whenever the copy is not the shipped one.
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
     * **Missing was not enough, and that cost a whole afternoon.** The
     * condition used to be "the file is not there", so a `npm run build` left
     * the bench holding the previous stylesheet and the suite failed on the
     * kit's staleness guard — in the middle of tests about something else
     * entirely. Comparing the bytes is what the guard itself compares, so the
     * bench now republishes for exactly the reasons the kit raises.
     *
     * **The condition is on the files, not on a process flag**, so it recovers
     * on its own if something removes or replaces a copy along the way. A flag
     * would leave every following test without a stylesheet, and their failure
     * would not speak of it.
     *
     * Before any of this, the kit's files were only there because a
     * `vendor:publish` run by hand one day had left them. A `composer install`
     * wiped them, and the suite fell over an error that said nothing about
     * itself.
     */
    private function publishTheCompiledFiles(): void
    {
        // Every file either package ships, read from its directory: a file the
        // comparison did not know of would never be republished, the others
        // being in order enough to conclude.
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
        return [
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

    /** Whether this process has already asked for its database and its removal. */
    private static bool $workerDatabaseClaimed = false;

    /**
     * Creates the worker's database when it is not there yet, **and arranges
     * for it to go away when this process ends**.
     *
     * Paratest hands out tokens, never databases, and asking the developer to
     * create eight by hand is a step that will be forgotten on the next machine.
     *
     * **The removal is the part that was missing, and it cost a red suite.**
     * Nothing ever dropped these: a run at sixteen processes left sixteen
     * databases, and the next run at four left twelve of them behind for good.
     * Measured 2026-09-14 · forty-four worker databases across this package and
     * falcon-booking, carrying 738 tables — 38 % of everything on the server —
     * against a `table_definition_cache` of 600. MySQL then evicts a table
     * definition between the preparing and the executing of a statement, and
     * answers « 1615 Prepared statement needs to be re-prepared » to a query
     * that has nothing wrong with it. It fell in a `tearDown`, on a test about
     * erasure, and looked like anything but what it was.
     *
     * At the end of the process rather than at the start · dropping at the start
     * would still leave every database of a wider run behind it.
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

        /*
         * A shutdown function rather than a `tearDownAfterClass`: paratest runs
         * one process per worker, and this has to fire once the whole share of
         * that worker is done, not after each class.
         */
        register_shutdown_function(static function () use ($name): void {
            try {
                self::server()->exec("DROP DATABASE IF EXISTS `{$name}`");
            } catch (Throwable) {
                // An interrupted run leaves it; the next one with the same token
                // drops it at its own end. Never fail a suite over housekeeping.
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

            // InnoDB, said out loud: a local MySQL installation set to MyISAM
            // refuses the composite indexes this package lays on its events,
            // and the error comes out in the middle of a migration.
            'engine' => 'InnoDB',
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->usePublicPath(self::publishedDirectoryFor(self::parallelToken()));

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
            $config->set('analytics.layouts.admin', null);
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
     * process rather than one test.
     */
    private static bool $migratedThisRun = false;

    /**
     * Testbench calls this hook on every test, and `loadMigrationsFrom` makes
     * it migrate and roll back around each one: twenty migrations up and
     * twenty down, three hundred and sixty-seven times over. On MySQL every
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
