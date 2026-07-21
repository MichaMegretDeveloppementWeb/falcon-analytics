<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\EnvScaffolder;
use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'analytics:install {--force : Overwrite existing published files}';

    protected $description = 'Install Falcon Analytics: publish the config file, scaffold env variables and run the migrations.';

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

        $this->callSilently('vendor:publish', [
            '--tag' => 'analytics-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Published config/analytics.php');

        $this->scaffoldEnvFile(base_path('.env'));
        $this->scaffoldEnvFile(base_path('.env.example'));

        $this->call('migrate');

        $this->newLine();
        $this->components->info('Next steps');
        $this->components->bulletList([
            'Set your guards and consent cookie in the identity block of config/analytics.php.',
            'Exclude your consent cookie from encryption (bootstrap/app.php encryptCookies except) so it is readable.',
            'If the app runs behind a proxy, configure TrustProxies so the real client IP is used.',
            'Add the @analyticsScripts directive to the layouts you want to track.',
        ]);

        return self::SUCCESS;
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

        file_put_contents($path, rtrim($contents, "\n")."\n".$block);
        $this->components->task('Added analytics variables to '.basename($path));
    }
}
