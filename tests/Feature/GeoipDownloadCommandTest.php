<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class GeoipDownloadCommandTest extends TestCase
{
    private string $directory;

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/analytics');
        $this->target = $this->directory.'/test-'.uniqid().'.mmdb';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0o755, true);
        }

        config([
            'analytics.geoip.license_key' => 'test-key',
            'analytics.geoip.edition' => 'GeoLite2-City',
            'analytics.geoip.database_path' => $this->target,
            'analytics.geoip.download_url' => 'https://download.maxmind.example/geoip_download?edition_id={edition}&license_key={license_key}&suffix=tar.gz',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->target, $this->directory.'/GeoLite2-City-download.tar.gz'] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_downloads_extracts_and_installs_the_geolite2_city_database(): void
    {
        Http::fake([
            'download.maxmind.example/*' => Http::response(file_get_contents(__DIR__.'/../Fixtures/geolite2-city.tar.gz'), 200),
        ]);

        $this->artisan('analytics:geoip:download')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'edition_id=GeoLite2-City')
            && str_contains($request->url(), 'license_key=test-key'));

        $this->assertFileExists($this->target);
        $this->assertSame('CITY-DATABASE-BYTES', file_get_contents($this->target));
    }

    public function test_it_fails_when_no_licence_key_is_configured(): void
    {
        config(['analytics.geoip.license_key' => '']);

        $this->artisan('analytics:geoip:download')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_fails_cleanly_and_preserves_an_existing_database_when_the_source_errors(): void
    {
        file_put_contents($this->target, 'PREVIOUS-WORKING-DB');

        Http::fake([
            'download.maxmind.example/*' => Http::response('unauthorised', 401),
        ]);

        $this->artisan('analytics:geoip:download')->assertFailed();

        $this->assertSame('PREVIOUS-WORKING-DB', file_get_contents($this->target));
    }

    public function test_it_schedules_a_monthly_refresh_that_only_runs_when_a_licence_key_is_set(): void
    {
        $event = Collection::make(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'analytics:geoip:download'));

        $this->assertNotNull($event);
        $this->assertSame('0 4 1 * *', $event->expression);

        config(['analytics.geoip.license_key' => 'a-key']);
        $this->assertTrue($event->filtersPass(app()));

        config(['analytics.geoip.license_key' => '']);
        $this->assertFalse($event->filtersPass(app()));
    }
}
