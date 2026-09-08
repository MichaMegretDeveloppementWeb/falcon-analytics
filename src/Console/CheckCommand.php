<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\AnalyticsAssets;
use Falcon\UiKit\Installer\AssetEntry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
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
 * It is also what reads `analytics.assets` back, so a host that moves an
 * entrypoint learns that the import was left behind.
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
            $this->checkMigrations(),
            $this->checkMasterSwitch($config),
            $this->checkAssets(),
            $this->checkCollector(),
            $this->checkEndpoint($config),
            $this->checkModuleMiddleware($config),
            $this->checkModuleLayouts($config),
            $this->checkGeoip($config),
        ];
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
            return ['Migrations', 'OK', 'Aucune migration du package en attente.'];
        }

        $count = count($pending);
        $label = $count === 1
            ? '1 migration du package est en attente'
            : "{$count} migrations du package sont en attente";

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
     * The imports, in the entrypoints the host named. The paths come from
     * `analytics.assets`, so a host that moves an entrypoint fixes the
     * configuration and the diagnostic follows.
     *
     * A missing key is a fault and not an exemption: skipping it would report
     * « Assets OK » while nothing is imported.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkAssets(): array
    {
        $missing = [];

        foreach (AnalyticsAssets::hostImports() as $key => [$kind, $vendorPath]) {
            $configured = (string) config('analytics.assets.'.$key);

            if ($configured === '') {
                $missing[] = 'analytics.assets.'.$key.' n’est pas renseigné : relancez analytics:install';

                continue;
            }

            $entry = $kind === 'css' ? AssetEntry::css($configured) : AssetEntry::js($configured);

            if (! $entry->alreadyImports($vendorPath)) {
                $missing[] = $entry->relativePath.' : '
                    .($kind === 'css' ? "@import '" : "import '").$entry->importPath($vendorPath)."';";
            }
        }

        if ($missing !== []) {
            return [
                'Assets',
                'KO',
                // No em dash: the console is read by a person.
                'Import manquant. Ajoutez-le puis lancez npm run build · '.implode(' · ', $missing),
            ];
        }

        // The collector has no business in the back office's script: finding it
        // there skews every dashboard figure without breaking anything.
        //
        // Only when the two entrypoints are two files. The shipped
        // configuration points both at the same `resources/js/app.js`, where
        // the import asked for in one is necessarily in the other.
        $adminJs = (string) config('analytics.assets.admin_js');
        $webJs = (string) config('analytics.assets.web_js');
        $collector = AnalyticsAssets::hostImports()['web_js'][1];

        if ($adminJs !== '' && $adminJs !== $webJs && AssetEntry::js($adminJs)->alreadyImports($collector)) {
            return [
                'Assets',
                'KO',
                'Le collecteur est importé par '.$adminJs.', le script du back-office : '
                .'les visites de l’administration sont comptées comme celles du public. Retirez cet import.',
            ];
        }

        return ['Assets', 'OK', 'Les assets du paquet sont importés et compilés par l’application.'];
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
        $paths = [resource_path('views')];
        $finder = View::getFinder();

        // `getPaths()` belongs to the file finder and not to the interface: a
        // host wiring another one keeps the standard folder read above, and
        // loses only its extra locations.
        if ($finder instanceof FileViewFinder) {
            foreach ($finder->getPaths() as $path) {
                if (! str_contains(str_replace('\\', '/', $path), '/vendor/')) {
                    $paths[] = $path;
                }
            }
        }

        foreach (array_unique($paths) as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (str_contains((string) File::get($file->getPathname()), '@analyticsConfig')) {
                    return ['Collecteur', 'OK', 'La directive @analyticsConfig est posée dans vos vues.'];
                }
            }
        }

        return [
            'Collecteur',
            'KO',
            'Aucune vue ne porte @analyticsConfig : aucune visite n’est mesurée. '
            .'Posez la directive dans le gabarit de votre site public, avant la fermeture de body.',
        ];
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
     * The two modules and what guards them. An explicitly empty list mounts the
     * screens with neither session nor authentication; the log says so at boot,
     * where nobody reads it.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkModuleMiddleware(Config $config): array
    {
        $exposed = [];

        foreach (['dashboard' => 'Tableau de bord', 'marketing' => 'Marketing'] as $module => $label) {
            if ($config->get('analytics.'.$module.'.middleware') === []) {
                $exposed[] = $label.' (analytics.'.$module.'.middleware)';
            }
        }

        if ($exposed === []) {
            return ['Protection', 'OK', 'Les deux modules sont montés derrière un middleware.'];
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
     * every screen of the module on the first visit and never before.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkModuleLayouts(Config $config): array
    {
        $missing = [];

        foreach (['dashboard' => 'Tableau de bord', 'marketing' => 'Marketing'] as $module => $label) {
            $layout = $config->get('analytics.'.$module.'.layout');

            if (is_string($layout) && $layout !== '' && ! View::exists($layout)) {
                $missing[] = $label.' : la vue '.$layout.' n’existe pas';
            }
        }

        if ($missing === []) {
            return ['Gabarits', 'OK', 'Les gabarits nommés existent, ou les modules utilisent celui du paquet.'];
        }

        return ['Gabarits', 'KO', implode(' · ', $missing)];
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
