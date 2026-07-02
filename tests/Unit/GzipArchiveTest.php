<?php

use Falcon\Analytics\Support\GzipArchive;

it('stream-decompresses a gzip archive into a target file', function () {
    $archive = tempnam(sys_get_temp_dir(), 'fa-gz-');
    $target = tempnam(sys_get_temp_dir(), 'fa-out-');
    file_put_contents($archive, gzencode('decompressed-mmdb-payload'));

    GzipArchive::extractTo($archive, $target);

    expect(file_get_contents($target))->toBe('decompressed-mmdb-payload');

    @unlink($archive);
    @unlink($target);
});

it('throws when the archive is missing', function () {
    $target = tempnam(sys_get_temp_dir(), 'fa-out-');

    expect(fn () => GzipArchive::extractTo('/does/not/exist.gz', $target))
        ->toThrow(RuntimeException::class);

    @unlink($target);
});
