<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SweepCommand extends Command
{
    protected $signature = 'analytics:sweep';

    protected $description = 'Close analytics sessions that have been idle past the timeout.';

    public function handle(SessionWriteRepository $sessions): int
    {
        $timeout = (int) config('analytics.session.timeout_minutes');

        try {
            $closed = $sessions->closeIdleSessions(CarbonImmutable::now()->subMinutes($timeout), $timeout);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics sweep failed.', ['exception' => $e]);
            $this->components->error('The sweep failed; see the analytics log channel.');

            return self::FAILURE;
        }

        $this->components->info("Closed {$closed} idle session(s).");

        return self::SUCCESS;
    }
}
