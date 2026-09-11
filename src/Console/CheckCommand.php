<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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
            $this->checkCollector(),
            $this->checkEndpoint($config),
            $this->checkModuleMiddleware($config),
            $this->checkAreaLayout($config),
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

    // Le controle des assets a ete retire ici, et il reviendra autrement.
    //
    // Il verifiait que l'hote avait bien importe les sources du paquet dans ses
    // propres entrees, et que le collecteur n'etait pas tombe dans le script du
    // back-office. Ce modele n'existe plus · le paquet compile et publie ses
    // fichiers, et l'hote n'importe rien. Ce qu'il faudra verifier a la place —
    // qu'une copie publiee correspond au fichier livre — appartient au
    // sous-chantier de la compilation, qui les fera naitre.

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
                if (str_contains(File::get($file->getPathname()), '@analyticsConfig')) {
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
        $layout = $config->get('analytics.admin.layout');

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
