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
     * Publier les fichiers compilés, quand ils manquent.
     *
     * **Le kit refuse de bâtir l'adresse d'un fichier que l'hôte n'a pas
     * publié**, et il a raison · une copie absente ou périmée servirait la
     * feuille du mois dernier sans un mot. L'application d'essai est un hôte
     * comme un autre, et elle doit donc publier.
     *
     * Ça passe par la commande réelle plutôt que par une copie écrite ici ·
     * une copie ne dirait rien de l'étiquette ni du dossier de destination, et
     * c'est justement ce qu'on veut tenir.
     *
     * **La condition porte sur les fichiers, pas sur un drapeau de processus.**
     * Elle se rattrape donc toute seule si quelque chose retire la copie en
     * cours de route ; un drapeau, lui, laisserait tous les essais suivants
     * sans feuille, et leur échec ne parlerait pas de lui.
     *
     * Avant, les fichiers du kit n'étaient là que parce qu'un `vendor:publish`
     * lancé un jour à la main les y avait laissés. Un `composer install` les
     * effaçait, et la suite tombait sur une erreur qui ne parlait pas d'elle.
     */
    private function publishTheCompiledFiles(): void
    {
        $expected = [
            public_path('vendor/falcon/ui/ui.css'),
            public_path('vendor/falcon/analytics/analytics.css'),
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
     * Une variable d'environnement, ou le repli quand elle ne dit rien.
     *
     * `getenv` rend `false` quand la variable est absente et `''` quand elle est
     * posee vide · sur un poste, les deux veulent dire « je n'ai rien choisi ».
     * Le mot de passe fait exception et vaut bien la chaine vide, ce que le
     * repli lui rend.
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

            $config->set('analytics.admin.middleware', ['web', 'auth:admin']);
            $config->set('analytics.admin.layout', null);
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
