<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Enums\GeoStatus;
use Falcon\Analytics\Support\GeoResolver;
use Illuminate\Console\Command;

/**
 * Say why localities are missing.
 *
 * Geolocation degrades to an empty location whatever goes wrong: no database, a truncated one, a
 * private address. All three showed the same blank column, and the only way to tell them apart
 * was to read the source.
 */
final class GeoipCheckCommand extends Command
{
    protected $signature = 'analytics:geoip:check {ip? : Une adresse à résoudre ; à défaut, une adresse publique connue}';

    protected $description = 'Dit si la base GeoIP est utilisable, et pourquoi une adresse résout ou non.';

    /** A well-known public resolver: always in the database, so it isolates local misconfiguration. */
    private const PROBE = '9.9.9.9';

    public function handle(GeoResolver $resolver): int
    {
        $path = (string) config('analytics.geoip.database_path');
        $devIp = trim((string) config('analytics.geoip.dev_ip'));
        // `argument()` may return an array for a variadic argument. This one is
        // not, but the signature does not say so.
        $given = $this->argument('ip');
        $ip = is_string($given) && $given !== '' ? $given : self::PROBE;

        $this->components->twoColumnDetail('Database', is_file($path)
            ? $path.' ('.$this->humanSize((int) filesize($path)).', '.date('Y-m-d', (int) filemtime($path)).')'
            : $path.' (missing)');

        $this->components->twoColumnDetail('Development address', $devIp === '' ? 'not set' : $devIp);
        $this->components->twoColumnDetail('Licence key', trim((string) config('analytics.geoip.license_key')) === '' ? 'not set' : 'set');

        $status = $resolver->status($ip);
        $this->components->twoColumnDetail('Resolving '.$ip, $status->consoleLabel());

        if ($status === GeoStatus::Ready) {
            $location = $resolver->locate($ip);

            $parts = array_filter(
                [$location->city, $location->region, $location->country],
                static fn (?string $part): bool => $part !== null && $part !== '',
            );

            $this->components->twoColumnDetail('Locality', $parts === [] ? 'none' : implode(', ', $parts));

            $this->newLine();
            $this->components->info('Geolocation is working.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->warn(ucfirst($status->consoleLabel()).'. '.($status->consoleHint() ?? ''));

        return self::FAILURE;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? round($bytes / 1_048_576).' MB'
            : round($bytes / 1024).' KB';
    }
}
