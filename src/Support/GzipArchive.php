<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use RuntimeException;

final class GzipArchive
{
    /**
     * Stream-decompress a gzip archive into a target file with constant memory,
     * suitable for the large geolocation database.
     */
    public static function extractTo(string $archive, string $target): void
    {
        if (! is_file($archive)) {
            throw new RuntimeException('The gzip archive does not exist.');
        }

        $in = @gzopen($archive, 'rb');
        if ($in === false) {
            throw new RuntimeException('The gzip archive could not be opened.');
        }

        $out = @fopen($target, 'wb');
        if ($out === false) {
            gzclose($in);
            throw new RuntimeException('The target file could not be opened for writing.');
        }

        try {
            while (! gzeof($in)) {
                $chunk = gzread($in, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('The gzip archive is corrupt.');
                }
                fwrite($out, $chunk);
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }
}
