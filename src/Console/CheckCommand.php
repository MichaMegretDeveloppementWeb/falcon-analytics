<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\DatabaseEngine;
use Falcon\Ui\Assets;
use Falcon\Ui\Exceptions\UiException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use Throwable;

/**
 * Diagnoses an installation.
 *
 * Separate from the installer on purpose: most of what breaks an installation
 * breaks it later, when configuration changes or an entrypoint is rewritten.
 *
 * Analytics fails quietly, which is why this matters more here than for a
 * package that draws screens. A collector that is never rendered, an ingestion
 * route behind the wrong middleware, a master switch left off: every one of
 * them leaves working screens showing an empty dashboard, and an empty
 * dashboard reads as « nobody came » rather than « nothing was measured ».
 *
 * @internal
 */
final class CheckCommand extends Command
{
    protected $signature = 'analytics:check';

    protected $description = 'Vérifie que falcon/analytics est correctement installé et opérationnel.';

    public function handle(Config $config): int
    {
        $rows = [];
        $blocking = 0;

        foreach ($this->checks($config) as [$label, $status, $detail]) {
            $rows[] = [$label, $status, $detail];

            if ($status === 'KO') {
                $blocking++;
            }
        }

        $this->table(['Point', 'État', 'Détail'], $rows);

        if ($blocking > 0) {
            $label = $blocking === 1 ? '1 point bloquant' : "{$blocking} points bloquants";

            $this->components->error($label);

            return self::FAILURE;
        }

        $this->components->info('Installation valide.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function checks(Config $config): array
    {
        return [
            $this->checkDatabaseEngine(),
            $this->checkMigrations(),
            $this->checkMasterSwitch($config),
            $this->checkCollector(),
            $this->checkEndpoint($config),
            $this->checkCollectorSession(),
            $this->checkModuleMiddleware($config),
            $this->checkAreaLayout($config),
            $this->checkPublishedAssets(),
            $this->checkIdentity($config),
            $this->checkDeclarations(),
            $this->checkRetention($config),
            $this->checkMarketingCeiling($config),
            $this->checkSummaries(),
            $this->checkProxy(),
            $this->checkGeoip($config),
        ];
    }

    /**
     * Whether the events and funnels files load whole.
     *
     * A file that stops on an error keeps what came before it and ignores the
     * rest, so the conversions declared after the error vanish from the
     * screens. Loading never raises, by design, which is why it is said here.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkDeclarations(): array
    {
        $failures = array_values(array_filter(
            [app(EventRegistry::class)->failure(), app(FunnelRegistry::class)->failure()],
            fn (?string $failure): bool => $failure !== null,
        ));

        if ($failures === []) {
            return ['Déclarations', 'OK', 'Les fichiers des événements et des tunnels se lisent en entier.'];
        }

        return [
            'Déclarations',
            'KO',
            'Lecture arrêtée sur une erreur, et tout ce qui est déclaré ensuite est ignoré · '.implode(' · ', $failures),
        ];
    }

    /**
     * The retention setting, read the way the erasing reads it.
     *
     * A number of days, or `null` to never erase. **Zero and negatives are
     * refused** rather than taken for « keep everything », which is the
     * opposite of what one writes them for — and the erasing would then stop
     * every night while saying so to a log nobody reads.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkRetention(Config $config): array
    {
        $days = $config->get('analytics.retention_days');

        if ($days === null) {
            return ['Conservation', 'OK', 'Aucune : le détail est gardé indéfiniment.'];
        }

        if (! is_int($days) || $days < 1) {
            return [
                'Conservation',
                'KO',
                'analytics.retention_days doit être un nombre de jours d’au moins 1, ou null pour ne jamais '
                .'effacer. Tant que ce n’est pas le cas, rien n’est effacé et la commande échoue chaque nuit.',
            ];
        }

        return [
            'Conservation',
            'OK',
            "Le pas à pas des sessions est gardé {$days} jours. Au-delà, seuls les pages vues et clics "
            .'anonymes sont effacés ; les événements nommés restent, et aucun autre écran ne bouge.',
        ];
    }

    /**
     * The marketing ceiling, read the way the marketing screens read it.
     *
     * A value that is not a whole number of sessions is refused there rather
     * than replaced by one nobody chose, so those screens stop · this says why.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkMarketingCeiling(Config $config): array
    {
        $ceiling = $config->get('analytics.marketing.max_sessions');

        if (! is_int($ceiling) || $ceiling < 1) {
            return [
                'Marketing',
                'KO',
                'analytics.marketing.max_sessions doit être un nombre de sessions d’au moins 1. '
                .'Tant que ce n’est pas le cas, les écrans marketing ne s’affichent pas.',
            ];
        }

        return [
            'Marketing',
            'OK',
            'Les écrans marketing lisent au plus '.number_format($ceiling, 0, ',', ' ').' sessions par période.',
        ];
    }

    /**
     * Whether the summarising keeps up, which is how a dead scheduler shows.
     *
     * **Nothing else says it.** When the scheduler stops, the erasing stops
     * with it — nothing is lost, by design — and the screens go on answering
     * from the rows. The only visible trace is this backlog growing, and the
     * catch-up on a screen load hides even that while an administrator visits.
     *
     * One day waiting is the normal state between midnight and the nightly run.
     * More than that means either a scheduler that is not running, or an
     * installation still working through the history it had before the
     * summaries existed — and the two are told apart by watching the number
     * fall, which is what the message asks for.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkSummaries(): array
    {
        try {
            $waiting = count(app(DailyCountArchiver::class)->pendingDays());
        } catch (Throwable) {
            return ['Résumés', 'KO', 'Impossible de lire l’état des résumés : la base ne répond pas comme attendu.'];
        }

        if ($waiting <= 1) {
            return ['Résumés', 'OK', 'À jour. Les jours clos sont résumés avant que leur détail ne soit effacé.'];
        }

        return [
            'Résumés',
            'À voir',
            "{$waiting} jour(s) clos attendent d’être résumés. Rien n’est perdu — l’effacement refuse un jour "
            .'non résumé — mais ce nombre doit baisser d’un jour à l’autre. S’il ne baisse pas, votre '
            .'planificateur ne tourne pas : vérifiez que schedule:run est déclenché chaque minute. '
            .'Pour rattraper tout de suite : php artisan analytics:archive.',
        ];
    }

    /**
     * The compiled stylesheet, as the application serves it.
     *
     * The package compiles and ships; the application publishes a copy and
     * serves that one. In between, the copy can be missing — an installation
     * that never published — or older than the package's last update.
     *
     * **The kit already holds this check**, and raises while building the
     * address, naming the command to run. This one does not repeat it: it
     * triggers it, here rather than on the first visit. That is all it brings,
     * and it is enough — a missing copy otherwise only shows the day someone
     * opens a screen, which can be long after the deployment that forgot it.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkPublishedAssets(): array
    {
        try {
            Assets::url('analytics', 'analytics.css');
        } catch (UiException $e) {
            return ['Feuille publiée', 'KO', $e->getMessage()];
        }

        return ['Feuille publiée', 'OK', 'La copie servie correspond au fichier que le paquet livre.'];
    }

    /**
     * First of the checks, and before the migrations on purpose: the engine
     * decides whether they mean anything at all.
     *
     * A connection can change after an install — a host moves its database, or
     * points a second environment somewhere else — so this is checked at every
     * deployment rather than only once.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkDatabaseEngine(): array
    {
        $driver = DatabaseEngine::current();

        if (! DatabaseEngine::isSupported($driver)) {
            return ['Base de données', 'KO', DatabaseEngine::refusal($driver)];
        }

        return ['Base de données', 'OK', "La connexion est en « {$driver} », que le paquet prend en charge."];
    }

    /**
     * A pending migration of the package is not a detail: the collector writes
     * to tables it creates, so announcing a coherent installation while one is
     * missing points every later error at the wrong cause.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkMigrations(): array
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');

            $ran = $migrator->getRepository()->getRan();
        } catch (Throwable) {
            return ['Migrations', 'KO', 'Impossible de lire la table des migrations. La base de données n’est pas initialisée.'];
        }

        $pending = [];

        foreach (File::files(__DIR__.'/../../database/migrations') as $file) {
            $name = $file->getFilenameWithoutExtension();

            if (! in_array($name, $ran, strict: true)) {
                $pending[] = $name;
            }
        }

        if ($pending === []) {
            return ['Migrations', 'OK', 'Aucune migration du paquet en attente.'];
        }

        $count = count($pending);
        $label = $count === 1
            ? '1 migration du paquet est en attente'
            : "{$count} migrations du paquet sont en attente";

        return ['Migrations', 'KO', $label.', dont '.$pending[0].'. Exécutez php artisan migrate.'];
    }

    /**
     * The master switch. Off is a legitimate state, but its symptom is an empty
     * dashboard, which does not tell « nobody came » from « nothing was
     * measured ».
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkMasterSwitch(Config $config): array
    {
        if ($config->get('analytics.enabled') === true) {
            return ['Interrupteur', 'OK', 'La mesure est active.'];
        }

        return [
            'Interrupteur',
            'KO',
            'analytics.enabled est à false : rien n’est mesuré et le collecteur n’est pas rendu. '
            .'Posez ANALYTICS_ENABLED=true.',
        ];
    }

    /**
     * The directive that lays the collector down, somewhere in the host's
     * views. The point most often missing, and the only one whose symptom is
     * strictly invisible: screens work, routes answer, tables exist, and not a
     * single visit arrives.
     *
     * Searched across every host view rather than in a named layout, the
     * package not knowing which one carries the public site. Package views are
     * skipped, ours included: finding the directive under `vendor/` would say
     * « laid down » about someone else's file.
     *
     * `resource_path('views')` is read whatever happens, before that filter: on
     * a test bench the host's views live under `vendor/`, and the general rule
     * would skip them silently.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkCollector(): array
    {
        ['found' => $found, 'stale' => $staleDirective] = $this->collectorDirectivesIn($this->hostViewPaths());

        if ($staleDirective !== null) {
            return [
                'Collecteur',
                'KO',
                "La vue {$staleDirective} porte encore @analyticsConfig, qui n’existe plus. "
                .'Blade recopie une directive inconnue telle quelle : ce texte s’affiche sur les '
                .'pages concernées, et elles ne sont pas mesurées. Renommez-la en @analyticsCollector.',
            ];
        }

        if ($found) {
            return ['Collecteur', 'OK', 'La directive @analyticsCollector est posée dans vos vues.'];
        }

        return [
            'Collecteur',
            'KO',
            'Aucune vue ne porte @analyticsCollector : aucune visite n’est mesurée. '
            .'Posez la directive dans le gabarit de votre site public, à l’endroit qui vous arrange : '
            .'le script est différé, donc sa place dans la page ne change rien.',
        ];
    }

    /**
     * The host's view folders · the standard one, then every other location
     * the file finder knows outside `vendor/`. A host wiring another finder
     * keeps the standard folder and loses only its extra locations.
     *
     * @return list<string>
     */
    private function hostViewPaths(): array
    {
        $paths = [resource_path('views')];
        $finder = View::getFinder();

        if ($finder instanceof FileViewFinder) {
            foreach ($finder->getPaths() as $path) {
                if (! str_contains(str_replace('\\', '/', $path), '/vendor/')) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Whether a view carries the directive, and the first one still on its old
     * name, `@analyticsConfig`, as a relative path with forward slashes.
     *
     * Blade copies an unknown directive to the output as it stands, so a view
     * left on the old name prints it to visitors. Every file is read even once
     * the directive is found · a host halfway through the rename has one layout
     * on each name.
     *
     * @param  list<string>  $paths
     * @return array{found: bool, stale: ?string}
     */
    private function collectorDirectivesIn(array $paths): array
    {
        $found = false;
        $stale = null;

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $contents = File::get($file->getPathname());

                $found = $found || str_contains($contents, '@analyticsCollector');

                if ($stale === null && str_contains($contents, '@analyticsConfig')) {
                    $stale = str_replace('\\', '/', $file->getRelativePathname());
                }
            }
        }

        return ['found' => $found, 'stale' => $stale];
    }

    /**
     * The route that receives the collector's batches. The package declares it,
     * so what is checked is that the configured path is the one it carries: an
     * `endpoint` changed without a new build leaves a collector talking to an
     * address that answers 404.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkEndpoint(Config $config): array
    {
        $endpoint = trim((string) $config->get('analytics.endpoint'), '/');

        if ($endpoint === '') {
            return ['Point d’entrée', 'KO', 'analytics.endpoint est vide : le collecteur n’a nulle part où écrire.'];
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $endpoint && in_array('POST', $route->methods(), strict: true)) {
                return ['Point d’entrée', 'OK', 'POST /'.$endpoint.' est monté.'];
            }
        }

        return [
            'Point d’entrée',
            'KO',
            'Aucune route POST ne répond sur /'.$endpoint.'. Videz le cache des routes (php artisan route:clear).',
        ];
    }

    /**
     * Whether the collector's route opens a session.
     *
     * Without consent — the default — the visitor's identifier lives in the
     * session, so a stack without one answers every beacon with an error: the
     * visitor's page does not suffer, and the screens stay empty with nothing
     * to say why. The router is asked what the route really runs, so a group
     * or an alias counts for what it carries.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkCollectorSession(): array
    {
        $route = Route::getRoutes()->getByName('analytics.web.ingest');

        if ($route === null) {
            return ['Session du collecteur', 'À voir', 'Vérifiée une fois la route du collecteur montée · voir le point d’entrée.'];
        }

        foreach (Route::gatherRouteMiddleware($route) as $middleware) {
            $class = is_string($middleware) ? Str::before($middleware, ':') : null;

            if ($class !== null && is_a($class, StartSession::class, true)) {
                return ['Session du collecteur', 'OK', 'La route du collecteur ouvre une session.'];
            }
        }

        return [
            'Session du collecteur',
            'KO',
            'La route du collecteur n’ouvre aucune session : sans consentement, l’identifiant du visiteur y vit, '
            .'et chaque envoi répond en erreur. Remettez StartSession, ou le groupe web, dans analytics.web.middleware.',
        ];
    }

    /**
     * The two screen groups and what guards them. An explicitly empty list
     * mounts them with neither session nor authentication; the log says so at
     * boot, where nobody reads it.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkModuleMiddleware(Config $config): array
    {
        $exposed = [];

        foreach (self::screenGroups() as $key => $label) {
            if ($config->get($key.'.middleware') === []) {
                $exposed[] = $label.' ('.$key.'.middleware)';
            }
        }

        if ($exposed === []) {
            return ['Protection', 'OK', 'Les deux groupes d’écrans sont montés derrière un middleware.'];
        }

        return [
            'Protection',
            'KO',
            'Monté sans aucune protection, donc publiquement joignable · '.implode(' · ', $exposed),
        ];
    }

    /**
     * The host's layout, when it names one. `null` mounts the screens in the
     * package's own shell; a name is a promise, and a missing layout drops
     * every screen of the area on the first visit and never before.
     *
     * A layout is a Blade COMPONENT, so it is never found under its own name:
     * `layout.admin` lives in `components/layout/admin.blade.php`, and a class
     * component lives in no view at all. The resolution Blade itself performs
     * answers for both; it raises when the name designates nothing.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkAreaLayout(Config $config): array
    {
        $layout = $config->get('analytics.layouts.admin');

        if (! is_string($layout) || $layout === '') {
            return ['Gabarit', 'OK', 'Les écrans utilisent la coquille du paquet.'];
        }

        $resolver = new ComponentTagCompiler(
            Blade::getClassComponentAliases(),
            Blade::getClassComponentNamespaces(),
            Blade::getFacadeRoot(),
        );

        try {
            $resolver->componentClass($layout);
        } catch (InvalidArgumentException) {
            return ['Gabarit', 'KO', 'Aucun composant ne répond au nom '.$layout.' : les écrans tomberaient à la première visite.'];
        }

        return ['Gabarit', 'OK', 'Le composant '.$layout.' existe.'];
    }

    /**
     * The two groups of screens the administration holds, by config block.
     *
     * Marketing sits inside the admin block rather than beside it: it is a
     * second entity of the same area, with its own address and its own guard.
     * The layout is not among them — it belongs to the area, and both entities
     * of the administration are drawn by the same one.
     *
     * @return array<string, string>
     */
    private static function screenGroups(): array
    {
        return [
            'analytics.admin' => 'Tableau de bord',
            'analytics.admin.marketing' => 'Marketing',
        ];
    }

    /**
     * The identity block, which is the one thing a host still fills by hand.
     *
     * **A guard named here that does not exist is dropped in silence.** The
     * subject resolution filters the configured names against `auth.guards`,
     * which is the right behaviour at runtime — a typo must not break a page —
     * and the worst possible behaviour for whoever set it: every visitor stays
     * anonymous, no screen is empty, and nothing says why.
     *
     * The named columns are checked the same way. They are read from the
     * guard's own table, and a column that is not there yields no name: the
     * screens then show « Client #12 » forever.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkIdentity(Config $config): array
    {
        $unknown = $this->guardsThatDoNotExist($config);

        if ($unknown !== []) {
            return [
                'Identité',
                'KO',
                'Ces guards sont nommés mais n’existent pas dans auth.guards : '.implode(', ', $unknown).'. '
                .'Ils sont ignorés en silence, donc personne n’est identifié ni exclu par eux.',
            ];
        }

        $missing = $this->columnsThatDoNotExist($config);

        if ($missing !== []) {
            return [
                'Identité',
                'KO',
                'Ces colonnes de nom sont introuvables : '.implode(', ', $missing).'. '
                .'Les écrans afficheront le libellé suivi de l’identifiant, sans jamais le nom.',
            ];
        }

        $subjects = (array) $config->get('analytics.identity.subject_guards', []);

        if ($subjects === []) {
            return ['Identité', 'OK', 'Aucun sujet suivi : les visiteurs restent anonymes, ce qui est un choix valable.'];
        }

        return ['Identité', 'OK', 'Guards et colonnes de nom vérifiés : '.implode(', ', array_map('strval', $subjects)).'.'];
    }

    /**
     * The guards the identity block names that `auth.guards` does not declare,
     * each followed by the key that names it.
     *
     * @return list<string>
     */
    private function guardsThatDoNotExist(Config $config): array
    {
        $declared = array_keys((array) $config->get('auth.guards', []));
        $unknown = [];

        foreach (['subject_guards', 'exclude_guards'] as $key) {
            foreach ((array) $config->get("analytics.identity.{$key}", []) as $guard) {
                if (is_string($guard) && ! in_array($guard, $declared, true)) {
                    $unknown[] = "{$guard} ({$key})";
                }
            }
        }

        return $unknown;
    }

    /**
     * The name columns a host declared that its own table does not carry.
     *
     * The table comes from the resolver rather than from a second derivation
     * here: it reads an explicit override, or the guard's auth provider model.
     * A guard whose source cannot be resolved at all is not a fault — a host
     * may want the label alone.
     *
     * @return list<string>
     */
    private function columnsThatDoNotExist(Config $config): array
    {
        $resolver = app(SubjectResolver::class);
        $missing = [];

        foreach ((array) $config->get('analytics.identity.subjects', []) as $guard => $settings) {
            if (! is_string($guard) || ! is_array($settings)) {
                continue;
            }

            $source = $resolver->sourceFor($guard);

            if ($source === null) {
                continue;
            }

            [$table] = $source;

            foreach ([...(array) ($settings['name'] ?? []), ...(array) ($settings['fallback'] ?? [])] as $column) {
                if (! is_string($column)) {
                    continue;
                }

                try {
                    $exists = Schema::hasColumn($table, $column);
                } catch (Throwable) {
                    // No reachable table is another point's business, not this
                    // one's: the migrations check speaks first.
                    return [];
                }

                if (! $exists) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return $missing;
    }

    /**
     * Trusted proxies, the last thing asked of a host, and the one that makes
     * a dashboard lie rather than stay empty.
     *
     * Behind a reverse proxy without that setting, every visit carries the
     * proxy's address: one country and one city for the whole site, and
     * `exclude_ips` matching either everybody or nobody. The numbers stay
     * plausible, which is what makes it expensive to find.
     *
     * **And the rate limit becomes one bucket for the whole site**, which is
     * the consequence that actually loses data rather than merely distorting
     * it. Laravel keys a guest's throttle on `domain|ip`, so every visitor
     * shares the 120 a minute and beyond that the beacons are refused — for
     * everyone at once, and silently, since a beacon's answer is not read.
     *
     * **Visitor counts are not affected by the address itself**, and saying
     * they were sent the reader looking for a bug in the wrong place · a
     * visitor is a cookie or a session id, never an address. See
     * `VisitorIdentityResolver`.
     *
     * It cannot be settled from the console — no request is in flight, and the
     * setting only shows when a forwarded header arrives. So this point says
     * what to look at rather than pretending to a verdict, and stays out of the
     * blocking count.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkProxy(): array
    {
        $trusted = config('trustedproxy.proxies') ?? config('app.trusted_proxies');

        if ($trusted !== null && $trusted !== [] && $trusted !== '') {
            return ['Proxy', 'OK', 'Des proxies de confiance sont déclarés : la vraie adresse du visiteur est lue.'];
        }

        return [
            'Proxy',
            'À voir',
            'Aucun proxy de confiance déclaré. Derrière un reverse proxy, toutes les visites porteront '
            .'son adresse · un seul pays, une seule ville, exclude_ips qui exclut tout le monde ou '
            .'personne, et surtout une limite de débit partagée par tout le site, qui refuse les envois '
            .'au-delà du seuil sans que rien ne le signale. Sans proxy, il n’y a rien à faire.',
        ];
    }

    /**
     * The geolocation database, and only when it is asked for. Without a
     * licence key the feature is off and its absence is no fault; a key laid
     * down without a downloaded database is one, and the symptom is an empty
     * « country » column that nothing explains.
     *
     * The fine detail belongs to `analytics:geoip:check`.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkGeoip(Config $config): array
    {
        if ((string) $config->get('analytics.geoip.license_key') === '') {
            return ['Géolocalisation', 'OK', 'Désactivée : aucune clé de licence MaxMind.'];
        }

        $database = (string) $config->get('analytics.geoip.database_path');

        if ($database !== '' && File::exists($database)) {
            return ['Géolocalisation', 'OK', 'La base est en place · analytics:geoip:check la détaille.'];
        }

        return [
            'Géolocalisation',
            'KO',
            'Une clé de licence est posée mais la base est absente : les visites n’ont pas de pays. '
            .'Exécutez php artisan analytics:geoip:download.',
        ];
    }
}
