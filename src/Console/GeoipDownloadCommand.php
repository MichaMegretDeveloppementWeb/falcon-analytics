<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Support\GzipArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GeoipDownloadCommand extends Command
{
    protected $signature = 'analytics:geoip:download {--month= : Edition to download as YYYY-MM (defaults to the current month)}';

    protected $description = 'Download the DB-IP City Lite database used to resolve visitor localities.';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        $url = str_replace('{month}', $month, (string) config('analytics.geoip.download_url'));
        $target = (string) config('analytics.geoip.database_path');
        $archive = $target.'.gz';
        $temporary = $target.'.tmp';

        $this->components->info("Downloading DB-IP City Lite ({$month}).");
        $this->ensureDirectory(dirname($target));

        try {
            $response = Http::timeout(180)->sink($archive)->get($url);

            if ($response->failed()) {
                throw new RuntimeException("the source returned HTTP {$response->status()}");
            }

            // Decompress to a temporary file, then swap it in: a failed run never
            // corrupts an existing, working database.
            GzipArchive::extractTo($archive, $temporary);
            rename($temporary, $target);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics GeoIP download failed.', [
                'exception' => $e,
                'url' => $url,
            ]);

            $this->components->error('Download failed: '.$e->getMessage());
            $this->line('If the current edition is not published yet, retry a previous month: --month='.now()->subMonthNoOverflow()->format('Y-m'));
            $this->line("Manual fallback: download {$url}, gunzip it, and place the .mmdb at {$target}");

            return self::FAILURE;
        } finally {
            foreach ([$archive, $temporary] as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }
        }

        $this->components->info("Database ready at {$target}");

        return self::SUCCESS;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }
}
