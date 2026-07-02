<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->target = storage_path('app/analytics/test-'.uniqid().'.mmdb');
    config([
        'analytics.geoip.database_path' => $this->target,
        'analytics.geoip.download_url' => 'https://geo.example.test/db-{month}.mmdb.gz',
    ]);
});

afterEach(function () {
    foreach ([$this->target, $this->target.'.gz', $this->target.'.tmp'] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('downloads and decompresses the database for the requested month', function () {
    Http::fake([
        'geo.example.test/*' => Http::response(gzencode('CITY-DATABASE-BYTES'), 200),
    ]);

    $this->artisan('analytics:geoip:download', ['--month' => '2026-06'])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://geo.example.test/db-2026-06.mmdb.gz');

    expect(is_file($this->target))->toBeTrue()
        ->and(file_get_contents($this->target))->toBe('CITY-DATABASE-BYTES');
});

it('fails cleanly and preserves an existing database when the source errors', function () {
    file_put_contents($this->target, 'PREVIOUS-WORKING-DB');

    Http::fake([
        'geo.example.test/*' => Http::response('unavailable', 503),
    ]);

    $this->artisan('analytics:geoip:download', ['--month' => '2026-06'])
        ->assertFailed();

    expect(file_get_contents($this->target))->toBe('PREVIOUS-WORKING-DB');
});
