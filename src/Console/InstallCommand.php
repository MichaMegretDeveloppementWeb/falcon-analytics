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
     * Environment variables appended to the host .env files, with their defaults.
     *
     * @var array<string, string>
     */
    private const ENV_DEFAULTS = [
        'ANALYTICS_ENABLED' => 'true',
        'ANALYTICS_GEOIP_DATABASE' => '',
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
            'Register the host callbacks (subject, consent, exclusion) in a service provider.',
            'Add the @analyticsScripts directive to the layouts you want to track.',
            'Declare your funnels in app/Analytics/funnels.php.',
            "Add a navigation link to route('analytics.dashboard').",
            'Schedule analytics:rollup and analytics:prune (an hourly cron is enough).',
        ]);

        return self::SUCCESS;
    }

    private function scaffoldEnvFile(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $block = EnvScaffolder::appendableBlock($contents, self::ENV_DEFAULTS);

        if ($block === '') {
            return;
        }

        file_put_contents($path, rtrim($contents, "\n")."\n".$block);
        $this->components->task('Added analytics variables to '.basename($path));
    }
}
