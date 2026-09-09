<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\AnalyticsAssets;
use Falcon\Analytics\Support\EnvScaffolder;
use Falcon\UiKit\Installer\AssetEntry;
use Illuminate\Console\Command;

/**
 * Installs the package. Three entrypoints are asked for whatever happens: the
 * back office's sheet and script, and the public site's script, which carries
 * the collector.
 *
 * It also installs falcon/ui-kit, which it depends on, passing it the back
 * office's paths: that is where its dashboards use it. The kit's steps are
 * idempotent, so the call costs nothing when everything is already in place.
 */
final class InstallCommand extends Command
{
    protected $signature = 'analytics:install
                            {--force : Écrase les fichiers déjà publiés}
                            {--admin-css= : Feuille de styles du back-office}
                            {--admin-js= : Script du back-office}
                            {--web-js= : Script du site public}';

    protected $description = 'Installe Falcon Analytics : publie la configuration, ajoute les variables d\'environnement et lance les migrations.';

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
     * The default offered is always Laravel's, with nothing detected: a fresh
     * project has only `app.css` and validates without reading, while a project
     * split into spaces types the name it chose.
     *
     * Three questions, and three answers that serve: two carry an import, the
     * third is passed to `ui-kit:install`. There is no public sheet to ask for,
     * the package shipping no public CSS.
     */
    private function collectEntries(): void
    {
        $this->entries = [
            'admin_css' => AssetEntry::css($this->pathFor('admin-css', 'Feuille de styles du back-office', 'resources/css/app.css')),
            'admin_js' => AssetEntry::js($this->pathFor('admin-js', 'Script du back-office', 'resources/js/app.js')),
            'web_js' => AssetEntry::js($this->pathFor('web-js', 'Script du site public', 'resources/js/app.js')),
        ];
    }

    private function pathFor(string $option, string $question, string $default): string
    {
        return (string) ($this->option($option) ?: $this->ask($question, $default));
    }

    /**
     * Composer has necessarily installed falcon/ui-kit, the manifest requiring
     * it, but its own steps may never have run. They run here, with the back
     * office's paths, where the dashboards live.
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
     * Files the collected paths into `config/analytics.php`, published just
     * before, replacing its defaults. A missing key leaves the file untouched.
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

            // And in memory: nothing reads the file back in this process.
            config()->set('analytics.assets.'.$key, $entry->relativePath);
        }

        file_put_contents($path, $contents);
        $this->components->task('Remembered your asset entries in config/analytics.php');
    }

    /**
     * The two lines: the dashboards' sheet, and the collector.
     *
     * The collector goes into the public site's script and never into the back
     * office's, an administrator's visits not being measured. It is standalone,
     * with no `import` and no npm dependency, and exits on its own when
     * `window.__falconAnalytics` is absent.
     */
    private function writeImports(): void
    {
        foreach (AnalyticsAssets::hostImports() as $key => [, $vendorPath]) {
            $this->report($this->entries[$key], $this->entries[$key]->import($vendorPath));
        }
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
