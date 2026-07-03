<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->dir = storage_path('app/analytics');
    $this->target = $this->dir.'/test-'.uniqid().'.mmdb';

    if (! is_dir($this->dir)) {
        mkdir($this->dir, 0o755, true);
    }

    config([
        'analytics.geoip.license_key' => 'test-key',
        'analytics.geoip.edition' => 'GeoLite2-City',
        'analytics.geoip.database_path' => $this->target,
        'analytics.geoip.download_url' => 'https://download.maxmind.example/geoip_download?edition_id={edition}&license_key={license_key}&suffix=tar.gz',
    ]);
});

afterEach(function () {
    $leftovers = [$this->target, $this->dir.'/GeoLite2-City-download.tar.gz'];

    foreach ($leftovers as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});

it('downloads, extracts and installs the GeoLite2 City database', function () {
    Http::fake([
        'download.maxmind.example/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/geolite2-city.tar.gz'), 200),
    ]);

    $this->artisan('analytics:geoip:download')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'edition_id=GeoLite2-City')
        && str_contains($request->url(), 'license_key=test-key'));

    expect(is_file($this->target))->toBeTrue()
        ->and(file_get_contents($this->target))->toBe('CITY-DATABASE-BYTES');
});

it('fails when no licence key is configured', function () {
    config(['analytics.geoip.license_key' => '']);

    $this->artisan('analytics:geoip:download')->assertFailed();

    Http::assertNothingSent();
});

it('fails cleanly and preserves an existing database when the source errors', function () {
    file_put_contents($this->target, 'PREVIOUS-WORKING-DB');

    Http::fake([
        'download.maxmind.example/*' => Http::response('unauthorised', 401),
    ]);

    $this->artisan('analytics:geoip:download')->assertFailed();

    expect(file_get_contents($this->target))->toBe('PREVIOUS-WORKING-DB');
});

it('schedules a monthly refresh that only runs when a licence key is set', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'analytics:geoip:download'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 1 * *');

    config(['analytics.geoip.license_key' => 'a-key']);
    expect($event->filtersPass(app()))->toBeTrue();

    config(['analytics.geoip.license_key' => '']);
    expect($event->filtersPass(app()))->toBeFalse();
});
