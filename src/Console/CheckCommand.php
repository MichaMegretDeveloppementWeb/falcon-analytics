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
 * **Analytics fails quietly**, which is why this matters more here than for a
 * package that draws screens. A collector that is never rendered, an ingestion
 * route behind the wrong middleware, a master switch left off: every one of
 * them leaves working screens showing an empty dashboard, and an empty
 * dashboard reads as « nobody came » rather than « nothing was measured ».
 *
 * It also gives `analytics.assets` its first reader. Until 2026-09-07 the
 * installer wrote that block and nothing ever looked at it again, so a host
 * that moved an entrypoint had no way of learning that the import had been
 * left behind.
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
     * L'interrupteur general, et pourquoi il merite une ligne.
     *
     * Eteint, rien n'est ingere et le collecteur n'est pas rendu · c'est un
     * etat legitime, en preproduction par exemple. Mais c'est aussi la
     * premiere chose qu'on oublie apres l'avoir coupe pour une demonstration,
     * et le symptome est un tableau de bord vide, qui ne dit pas la difference
     * entre « personne n'est venu » et « rien n'a ete mesure ».
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
     * Les deux imports, dans les entrees que l'hote a nommees.
     *
     * Les chemins viennent de `analytics.assets`, ou `analytics:install` les a
     * ranges · on ouvre le bon fichier au lieu de balayer `resources/`. Un hote
     * qui deplace une entree corrige la configuration, et le diagnostic suit.
     *
     * **Une cle absente est un defaut, pas une dispense.** Une configuration
     * publiee par une version anterieure ne porte pas ce bloc ; passer outre
     * dirait « Assets OK » alors que rien n'est importe, ce que ce point existe
     * precisement pour attraper.
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
                // Pas de tiret cadratin : la console est lue par une personne.
                'Import manquant. Ajoutez-le puis lancez npm run build · '.implode(' · ', $missing),
            ];
        }

        // Le collecteur n'a rien a faire dans le script du back-office · on ne
        // mesure pas les visites de la personne qui administre, et l'y trouver
        // fausse chaque chiffre du tableau de bord sans rien casser.
        //
        // **Seulement quand les deux entrees sont deux fichiers.** La
        // configuration livree les fait pointer le meme `resources/js/app.js`,
        // et l'import demande dans l'une est alors forcement dans l'autre :
        // crier au loup sur une installation par defaut apprend a ignorer ce
        // point, ce qui coute plus que le point ne rapporte. Un hote qui n'a
        // qu'un script ecarte de toute facon l'administration par
        // `identity.exclude_guards`.
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
     * La directive qui pose le collecteur, quelque part dans les vues.
     *
     * **C'est le point qui manque le plus souvent**, et le seul dont le
     * symptome soit rigoureusement invisible · les ecrans fonctionnent, les
     * routes repondent, les tables existent, et pas une visite n'arrive.
     *
     * Cherchee dans toutes les vues de l'hote plutot que dans un gabarit
     * nomme · le paquet ne sait pas lequel porte le site public, et deviner un
     * chemin rendrait un faux negatif chez qui l'a range ailleurs.
     *
     * **Les vues des paquets sont ecartees**, celles du notre comprises · la
     * directive appartient a l'hote, et la trouver dans un `vendor/` dirait
     * « c'est pose » sur le fichier de quelqu'un d'autre.
     *
     * `resource_path('views')` est lu quoi qu'il arrive, avant ce filtre · sur
     * un banc d'essai les vues de l'hote vivent sous `vendor/`, et la regle
     * generale les ecarterait sans rien dire.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function checkCollector(): array
    {
        $paths = [resource_path('views')];
        $finder = View::getFinder();

        // `getPaths()` appartient au chercheur de fichiers, pas a l'interface ·
        // un hote qui en branche un autre garde le dossier standard, lu
        // au-dessus, et perd seulement ses emplacements supplementaires.
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
     * La route qui recoit les lots du collecteur.
     *
     * Elle est declaree par le paquet, donc sa presence ne se discute pas · ce
     * qui se verifie, c'est que le chemin configure est bien celui qu'elle
     * porte. Un `endpoint` change en configuration sans nouveau build laisse un
     * collecteur qui parle a une adresse qui repond 404.
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
     * Les deux modules, et leur protection.
     *
     * Une liste **explicitement vide** monte les ecrans sans session ni
     * authentification · le journal le dit deja au demarrage, mais un
     * avertissement dans un fichier de log n'est lu par personne. Ici, il est
     * devant les yeux de qui pose la question.
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
     * Le gabarit de l'hote, quand il en nomme un.
     *
     * `null` monte les ecrans dans la coquille du paquet et se passe de tout
     * reglage. Un nom, en revanche, est une promesse · un gabarit absent fait
     * tomber chaque ecran du module a la premiere visite, et jamais avant.
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
     * La base de localisation, et seulement quand elle est demandee.
     *
     * Sans cle de licence, la fonctionnalite est eteinte et son absence n'est
     * pas un defaut · une cle posee sans base telechargee en est un, et le
     * symptome est une colonne « pays » vide que rien n'explique.
     *
     * Le detail fin appartient a `analytics:geoip:check`, qui dit pourquoi une
     * adresse donnee se resout ou non · on ne le recopie pas ici.
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
