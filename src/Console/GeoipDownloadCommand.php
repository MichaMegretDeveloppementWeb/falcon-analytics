<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $this->components->error('Set ANALYTICS_GEOIP_LICENSE_KEY in your .env. Get a free key at https://www.maxmind.com/en/geolite2/signup');

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

    /**
     * Unpack the one file we need, streaming.
     *
     * PharData was the obvious tool and it reads the whole archive into memory: a 32 MB download
     * blew past PHP's 128 MB default and the command died mid-extract, having already spent the
     * download. Ungzipping to a temp file then walking the tar keeps memory flat whatever the
     * archive weighs.
     */
    private function extractDatabase(string $archive, string $extractDir, string $edition): string
    {
        $this->ensureDirectory($extractDir);

        $tar = $extractDir.'/'.$edition.'.tar';
        $mmdb = $extractDir.'/'.$edition.'.mmdb';

        $this->gunzip($archive, $tar);
        $this->extractFromTar($tar, $edition.'.mmdb', $mmdb);

        @unlink($tar);

        return $mmdb;
    }

    private function gunzip(string $source, string $target): void
    {
        $in = @gzopen($source, 'rb');
        $out = @fopen($target, 'wb');

        if ($in === false || $out === false) {
            throw new RuntimeException('the downloaded archive could not be opened');
        }

        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, 1 << 20);

                if ($chunk === false) {
                    throw new RuntimeException('the downloaded archive is not readable gzip');
                }

                fwrite($out, $chunk);
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }

    /**
     * Copy out the first entry whose name ends with $needle.
     *
     * A tar is a flat sequence of 512-byte headers followed by their payload, padded to the next
     * 512 boundary. MaxMind nests the database under a dated folder, hence the suffix match
     * rather than an exact one.
     */
    private function extractFromTar(string $tar, string $needle, string $target): void
    {
        $in = @fopen($tar, 'rb');

        if ($in === false) {
            throw new RuntimeException('the archive could not be read');
        }

        try {
            while (($header = fread($in, 512)) !== false && strlen($header) === 512) {
                $name = rtrim(substr($header, 0, 100), "\0");

                // Two zeroed blocks close a tar; the first empty name is enough to stop.
                if ($name === '') {
                    break;
                }

                $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
                $padded = (int) (ceil($size / 512) * 512);

                if (! str_ends_with($name, $needle)) {
                    fseek($in, $padded, SEEK_CUR);

                    continue;
                }

                $this->copyBytes($in, $target, $size);

                return;
            }
        } finally {
            fclose($in);
        }

        throw new RuntimeException("the archive did not contain {$needle}");
    }

    /** @param  resource  $in */
    private function copyBytes($in, string $target, int $size): void
    {
        $out = @fopen($target, 'wb');

        if ($out === false) {
            throw new RuntimeException("{$target} could not be written");
        }

        try {
            $remaining = $size;

            while ($remaining > 0) {
                $chunk = fread($in, (int) min(1 << 20, $remaining));

                if ($chunk === false || $chunk === '') {
                    throw new RuntimeException('the archive ended before the database did');
                }

                fwrite($out, $chunk);
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($out);
        }
    }

    private function cleanup(string $archive, string $extractDir): void
    {
        @unlink($archive);

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
