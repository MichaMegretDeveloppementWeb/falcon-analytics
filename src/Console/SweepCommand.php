<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Illuminate\Console\Command;

final class SweepCommand extends Command
{
    protected $signature = 'analytics:sweep';

    protected $description = 'Close analytics sessions that have been idle past the timeout.';

    public function handle(SessionWriteRepository $sessions): int
    {
        $timeout = (int) config('analytics.session.timeout_minutes');

        $closed = $sessions->closeIdleSessions(CarbonImmutable::now()->subMinutes($timeout), $timeout);

        $this->components->info("Closed {$closed} idle session(s).");

        return self::SUCCESS;
    }
}
