<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class GeoipDownloadCommand extends Command
{
    protected $signature = 'analytics:geoip:download';

    protected $description = 'Download the MaxMind GeoLite2 City database used to resolve visitor localities.';

    public function handle(): int
    {
        $key = trim((string) config('analytics.geoip.license_key'));
        $edition = (string) config('analytics.geoip.edition');
        $target = (string) config('analytics.geoip.database_path');

        if ($key === '') {
            $this->components->error('Set ANALYTICS_GEOIP_LICENSE_KEY in your .env — get a free key at https://www.maxmind.com/en/geolite2/signup');

            return self::FAILURE;
        }

        $url = str_replace(['{edition}', '{license_key}'], [$edition, rawurlencode($key)], (string) config('analytics.geoip.download_url'));
        $archive = dirname($target).'/'.$edition.'-download.tar.gz';
        $extractDir = dirname($target).'/'.$edition.'-extract';

        $this->components->info("Downloading MaxMind {$edition}.");
        $this->ensureDirectory(dirname($target));

        try {
            $response = Http::timeout(180)->sink($archive)->get($url);

            if ($response->failed()) {
                throw new RuntimeException($response->status() === 401
                    ? 'invalid or unauthorised licence key'
                    : "the source returned HTTP {$response->status()}");
            }

            // MaxMind nests the .mmdb under a dated folder; extract it to a temp
            // dir then swap it in, so a failed run never corrupts a working file.
            $mmdb = $this->extractDatabase($archive, $extractDir, $edition);
            rename($mmdb, $target);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics GeoIP download failed.', [
                'exception' => $e,
            ]);

            $this->components->error('Download failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $this->cleanup($archive, $extractDir);
        }

        $this->components->info("Database ready at {$target}");

        return self::SUCCESS;
    }

    private function extractDatabase(string $archive, string $extractDir, string $edition): string
    {
        (new PharData($archive))->extractTo($extractDir, null, true);

        $found = glob($extractDir.'/*/'.$edition.'.mmdb') ?: glob($extractDir.'/'.$edition.'.mmdb');

        if ($found === false || $found === []) {
            throw new RuntimeException("the archive did not contain {$edition}.mmdb");
        }

        return $found[0];
    }

    private function cleanup(string $archive, string $extractDir): void
    {
        if (is_file($archive)) {
            try {
                Phar::unlinkArchive($archive);
            } catch (Throwable) {
                @unlink($archive);
            }
        }

        if (! is_dir($extractDir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($extractDir);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }
}
