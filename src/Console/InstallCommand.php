<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'analytics:install {--force : Overwrite existing published files}';

    protected $description = 'Install Falcon Analytics: publish the config file and run the migrations.';

    public function handle(): int
    {
        $this->components->info('Installing Falcon Analytics.');

        $this->callSilently('vendor:publish', [
            '--tag' => 'analytics-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Published config/analytics.php');

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
}
