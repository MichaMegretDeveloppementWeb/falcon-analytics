<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\EnvScaffolder;
use Falcon\UiKit\Installer\AssetEntry;
use Illuminate\Console\Command;

/**
 * L'installation du paquet.
 *
 * **Les quatre questions sont posées quoi qu'il arrive.** Le paquet a deux
 * faces — des tableaux de bord, un collecteur sur le site public — et chacune a
 * une feuille et un script. Deux réponses ne servent pas encore ; elles sont
 * rangées dans la configuration, et la version qui en aura besoin ne
 * redemandera rien.
 *
 * **Il installe aussi falcon/ui-kit**, dont il dépend, en lui passant les
 * chemins du back-office : c'est là qu'il s'en sert, ses tableaux de bord étant
 * dessinés avec ses composants. Aucune vérification — les étapes du kit sont
 * idempotentes, donc l'appel ne coûte rien quand tout est déjà en place.
 */
final class InstallCommand extends Command
{
    protected $signature = 'analytics:install
                            {--force : Overwrite existing published files}
                            {--admin-css= : Feuille de styles du back-office}
                            {--admin-js= : Script du back-office}
                            {--web-css= : Feuille de styles du site public}
                            {--web-js= : Script du site public}';

    protected $description = 'Install Falcon Analytics: publish the config file, scaffold env variables and run the migrations.';

    private const ADMIN_STYLESHEET = 'vendor/falcon/analytics/resources/css/analytics-admin.css';

    private const COLLECTOR = 'vendor/falcon/analytics/resources/js/collector.js';

    /** @var array<string, AssetEntry> */
    private array $entries = [];

    /**
     * Every env variable the package reads, grouped and commented, appended to
     * the host .env files when missing (config/analytics.php stays the source
     * of truth; this only makes the knobs discoverable in place).
     *
     * @var list<array{comment: list<string>, entries: array<string, string>}>
     */
    private const ENV_GROUPS = [
        [
            'comment' => ['Master switch of the tracking (collector + ingestion).'],
            'entries' => ['ANALYTICS_ENABLED' => 'true'],
        ],
        [
            'comment' => [
                'Local geolocation (MaxMind GeoLite2 City). Free licence key:',
                'https://www.maxmind.com/en/geolite2/signup then: php artisan analytics:geoip:download',
                'Empty key = geolocation disabled (localities stay unknown).',
            ],
            'entries' => ['ANALYTICS_GEOIP_LICENSE_KEY' => ''],
        ],
        [
            'comment' => ['Path of the .mmdb database (empty = storage/app/analytics/GeoLite2-City.mmdb).'],
            'entries' => ['ANALYTICS_GEOIP_DATABASE' => ''],
        ],
        [
            'comment' => ['Local dev only: public IP substituted for private/reserved request IPs (127.0.0.1). Inert in production.'],
            'entries' => ['ANALYTICS_GEOIP_DEV_IP' => ''],
        ],
        [
            'comment' => [
                'Google Search Console (organic search queries): OAuth 2.0 web client',
                'with the Search Console API enabled, created in Google Cloud.',
                'Empty = the whole integration stays hidden.',
            ],
            'entries' => [
                'ANALYTICS_GSC_CLIENT_ID' => '',
                'ANALYTICS_GSC_CLIENT_SECRET' => '',
            ],
        ],
        [
            'comment' => ['Redirect URI override (empty = the package callback route, shown on the integrations screen).'],
            'entries' => ['ANALYTICS_GSC_REDIRECT' => ''],
        ],
    ];

    public function handle(): int
    {
        $this->components->info('Installing Falcon Analytics.');

        $this->collectEntries();
        $this->installTheKit();

        $this->callSilently('vendor:publish', [
            '--tag' => 'analytics-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Published config/analytics.php');

        $this->rememberEntries();
        $this->writeImports();

        $this->scaffoldEnvFile(base_path('.env'));
        $this->scaffoldEnvFile(base_path('.env.example'));

        if ($this->call('migrate') !== self::SUCCESS) {
            $this->components->error('The migrations failed; fix the database and re-run analytics:install.');

            return self::FAILURE;
        }

        $this->whatIsLeft();

        return self::SUCCESS;
    }

    // ─── Les chemins ─────────────────────────────────────────────

    /**
     * Le défaut proposé est toujours celui de Laravel, sans rien détecter.
     *
     * Un projet neuf n'a que `app.css` et valide sans lire ; un projet rangé en
     * espaces tape le nom qu'il a choisi.
     */
    private function collectEntries(): void
    {
        $this->entries = [
            'admin_css' => AssetEntry::css($this->pathFor('admin-css', 'Feuille de styles du back-office', 'resources/css/app.css')),
            'admin_js' => AssetEntry::js($this->pathFor('admin-js', 'Script du back-office', 'resources/js/app.js')),
            'web_css' => AssetEntry::css($this->pathFor('web-css', 'Feuille de styles du site public', 'resources/css/app.css')),
            'web_js' => AssetEntry::js($this->pathFor('web-js', 'Script du site public', 'resources/js/app.js')),
        ];
    }

    private function pathFor(string $option, string $question, string $default): string
    {
        return (string) ($this->option($option) ?: $this->ask($question, $default));
    }

    /**
     * falcon/ui-kit, dont ce paquet dépend.
     *
     * Composer l'a forcément installé — le `composer.json` l'exige — mais ses
     * étapes à lui n'ont peut-être jamais été jouées. On les joue donc, avec
     * les chemins du back-office : c'est là que les tableaux de bord vivent.
     */
    private function installTheKit(): void
    {
        $this->call('ui-kit:install', [
            '--css' => $this->entries['admin_css']->relativePath,
            '--js' => $this->entries['admin_js']->relativePath,
            '--no-interaction' => true,
        ]);
    }

    /**
     * Range les quatre chemins dans `config/analytics.php`.
     *
     * Publié juste avant, donc les valeurs par défaut y sont ; on les remplace
     * par les réponses. Une clé absente laisse le fichier intact.
     */
    private function rememberEntries(): void
    {
        $path = config_path('analytics.php');

        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);

        foreach ($this->entries as $key => $entry) {
            $contents = (string) preg_replace(
                "/('{$key}'\s*=>\s*)'[^']*'/",
                "$1'".$entry->relativePath."'",
                $contents,
                1,
            );

            // Et en mémoire · rien ne relit le fichier dans ce processus.
            config()->set('analytics.assets.'.$key, $entry->relativePath);
        }

        file_put_contents($path, $contents);
        $this->components->task('Remembered your asset entries in config/analytics.php');
    }

    /**
     * Les deux lignes · la feuille des tableaux de bord, le collecteur.
     *
     * **Le collecteur va dans le script du site public, pas dans celui du
     * back-office** · on ne mesure pas les visites de la personne qui
     * administre. Il est autonome — aucun `import`, aucune dépendance npm — et
     * il sort de lui-même quand `window.__falconAnalytics` est absent, donc
     * l'hôte n'a aucune condition à écrire.
     */
    private function writeImports(): void
    {
        $this->report($this->entries['admin_css'], $this->entries['admin_css']->import(self::ADMIN_STYLESHEET));
        $this->report($this->entries['web_js'], $this->entries['web_js']->import(self::COLLECTOR));
    }

    private function report(AssetEntry $entry, string $status): void
    {
        $this->components->twoColumnDetail($entry->relativePath, match ($status) {
            AssetEntry::CREATED => '<fg=green>créé, import ajouté</>',
            AssetEntry::ADDED => '<fg=green>import ajouté</>',
            default => '<fg=gray>déjà en place</>',
        });
    }

    private function whatIsLeft(): void
    {
        $this->newLine();
        $this->components->info('À faire');
        $this->components->bulletList([
            'Déclarer vos entrées dans vite.config.js et les charger par @vite dans vos gabarits',
            'Poser @analyticsConfig dans le gabarit public que vous voulez mesurer',
            'npm run build',
            'Renseigner vos guards et votre cookie de consentement dans le bloc identity de config/analytics.php',
            'Exclure ce cookie du chiffrement (bootstrap/app.php, encryptCookies except) pour qu’il soit lisible',
            'Derrière un proxy, régler TrustProxies pour que la vraie IP du client soit utilisée',
        ]);

        $this->components->warn('Sans @analyticsConfig, le collecteur est chargé mais ne mesure rien : il sort faute de configuration.');
    }

    private function scaffoldEnvFile(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $block = EnvScaffolder::appendableBlock($contents, self::ENV_GROUPS);

        if ($block === '') {
            return;
        }

        if (file_put_contents($path, rtrim($contents, "\n")."\n".$block) === false) {
            $this->components->warn('Could not write '.basename($path).'; append the analytics variables manually.');

            return;
        }

        $this->components->task('Added analytics variables to '.basename($path));
    }
}
