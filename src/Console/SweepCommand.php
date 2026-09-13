<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** @internal */
final class SweepCommand extends Command
{
    protected $signature = 'analytics:sweep';

    protected $description = 'Clôt les sessions inactives au-delà du délai.';

    public function handle(SessionWriteRepository $sessions): int
    {
        $timeout = (int) config('analytics.session.timeout_minutes');

        try {
            $closed = $sessions->closeIdleSessions(CarbonImmutable::now()->subMinutes($timeout), $timeout);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics sweep failed.', ['exception' => $e]);
            $this->components->error('La clôture a échoué ; voyez le canal de journal de l’analytique.');

            return self::FAILURE;
        }

        $this->components->info("{$closed} session(s) close(s) après {$timeout} minute(s) d’inactivité.");

        return self::SUCCESS;
    }
}
