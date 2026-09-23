<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\DatabaseEngine;
use Falcon\Analytics\Support\EnvScaffolder;
use Illuminate\Console\Command;

/**
 * Installs the package: publishes its settings, scaffolds the environment
 * variables it reads, and runs its migrations.
 *
 * **It asks nothing about your files, and writes into none of them.** It used
 * to ask for three entrypoints — the back office's sheet and script, the public
 * site's script — and write imports into them, because the packages shipped
 * sources for the host to compile. They compile their own now, so there is
 * nothing to import and nothing to ask.
 *
 * It also runs the kit's installer, which it depends on. Every step of both is
 * idempotent: running this again costs nothing.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    protected $signature = 'analytics:install
                            {--force : Écrase les fichiers déjà publiés}';

    protected $description = 'Installe Falcon Analytics : publie la configuration, ajoute les variables d\'environnement et lance les migrations.';

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
        /*
         * Before anything is written, and that is the whole point. A refusal
         * further down would leave a published config, published compiled
         * files, an environment file written and tables half worth having —
         * an installation that looks done and is not.
         */
        if (! DatabaseEngine::isSupported()) {
            $this->components->error(DatabaseEngine::refusal());

            return self::FAILURE;
        }

        $this->components->info('Installation de Falcon Analytics.');

        $this->installTheKit();

        $this->callSilently('vendor:publish', [
            '--tag' => 'analytics-config',
            '--force' => $this->option('force'),
        ]);
        $this->components->task('config/analytics.php publié');

        /*
         * The compiled files, forced on purpose: they are generated, so there
         * is nothing of the application's to preserve, and a copy left behind
         * is worse than none — the kit compares it to what the package ships
         * and raises on the first screen rather than serving last month's.
         *
         * Their own tag rather than `laravel-assets`, which would republish
         * every package of the suite. A deployment uses the wide one; an
         * install of this package uses this.
         */
        $this->callSilently('vendor:publish', ['--tag' => 'analytics-assets', '--force' => true]);
        $this->components->task('Feuille de styles et collecteur compilés publiés');

        $this->scaffoldEnvFile(base_path('.env'));
        $this->scaffoldEnvFile(base_path('.env.example'));

        if ($this->call('migrate') !== self::SUCCESS) {
            $this->components->error('Les migrations ont échoué ; corrigez la base et relancez analytics:install.');

            return self::FAILURE;
        }

        $this->whatIsLeft();

        return self::SUCCESS;
    }

    /**
     * Composer has necessarily installed falcon/ui-kit, the manifest requiring
     * it, but its own steps may never have run. They run here.
     */
    private function installTheKit(): void
    {
        $this->call('ui-kit:install', ['--no-interaction' => true]);
    }

    private function whatIsLeft(): void
    {
        $this->newLine();
        $this->components->info('À faire');
        $this->components->bulletList([
            'Poser @analyticsCollector dans le gabarit public que vous voulez mesurer',
            'Renseigner vos guards et votre cookie de consentement dans le bloc identity de config/analytics.php',
            'Derrière un proxy, régler TrustProxies pour que la vraie IP du client soit utilisée',
            'En développement · php artisan analytics:seed remplit les écrans de visites inventées',
        ]);

        $this->components->warn('Sans @analyticsCollector, aucune page n’est mesurée : le collecteur n’est même pas chargé.');
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
            $this->components->warn('Écriture de '.basename($path).' impossible ; ajoutez les variables de l’analytique à la main.');

            return;
        }

        $this->components->task('Variables de l’analytique ajoutées à '.basename($path));
    }
}
