<?php

use Falcon\Analytics\AnalyticsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('merges the package configuration', function () {
    expect(config('analytics.enabled'))->toBeTrue()
        ->and(config('analytics.retention_days'))->toBe(90)
        ->and(config('analytics.session.timeout_minutes'))->toBe(5)
        ->and(config('analytics.session.heartbeat_seconds'))->toBe(20)
        ->and(config('analytics.privacy.anonymize_ip'))->toBeFalse();
});

it('creates the core analytics tables', function () {
    expect(Schema::hasTable('falcon_analytics_visitors'))->toBeTrue()
        ->and(Schema::hasTable('falcon_analytics_sessions'))->toBeTrue()
        ->and(Schema::hasTable('falcon_analytics_events'))->toBeTrue();
});

it('defines the expected session columns', function () {
    expect(Schema::hasColumns('falcon_analytics_sessions', [
        'visitor_id', 'started_at', 'last_activity_at', 'ended_at',
        'ip', 'country', 'city', 'latitude', 'longitude',
        'device_type', 'browser', 'os', 'is_bot',
        'source', 'utm_source', 'subject_type', 'subject_id',
        'pageview_count', 'event_count',
    ]))->toBeTrue();
});

it('defines the expected event columns', function () {
    expect(Schema::hasColumns('falcon_analytics_events', [
        'session_id', 'visitor_id', 'occurred_at', 'type', 'name',
        'route', 'url', 'target_selector', 'target_text', 'props', 'value',
    ]))->toBeTrue();
});

it('registers the install command', function () {
    expect(Artisan::all())->toHaveKey('analytics:install');
});

it('adds the audit indexes on events and sessions', function () {
    $eventIndexes = collect(Schema::getIndexes('falcon_analytics_events'))->pluck('name');
    $sessionIndexes = collect(Schema::getIndexes('falcon_analytics_sessions'))->pluck('name');

    expect($eventIndexes)->toContain('fa_events_route_occurred_idx')
        ->toContain('fa_events_visitor_occurred_idx')
        ->and($sessionIndexes)->toContain('fa_sessions_country_idx');
});

it('registers the configured module middleware as Livewire-persistent', function () {
    // TestCase mounts the dashboard behind ['web', 'auth:admin'] and the
    // marketing module keeps its ['web', 'auth'] default; both must replay on
    // /livewire/update, while 'web' stays out (Livewire always runs it).
    $persistent = Livewire::getPersistentMiddleware();

    expect($persistent)->toContain('auth:admin')
        ->toContain('auth')
        ->not->toContain('web');
});

it('warns when a module is mounted with an empty middleware list', function () {
    config(['analytics.dashboard.middleware' => [], 'analytics.marketing.middleware' => []]);

    Log::shouldReceive('channel')->twice()->andReturnSelf();
    Log::shouldReceive('warning')
        ->twice()
        ->withArgs(fn (string $message): bool => str_contains($message, 'empty middleware list'));

    require dirname(__DIR__, 2).'/routes/analytics.php';

    expect(Route::has('analytics.overview'))->toBeTrue();
});

it('runs analytics:install for real against a temporary base path', function () {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fa-install-'.uniqid();
    File::makeDirectory($base.DIRECTORY_SEPARATOR.'config', 0755, true);
    File::put($base.DIRECTORY_SEPARATOR.'.env', "APP_NAME=Host\n");
    File::put($base.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Host\n");

    $this->app->setBasePath($base);
    // Re-boot the provider so the publish target follows the new base path.
    $this->app->register(AnalyticsServiceProvider::class, force: true);

    try {
        $this->artisan('analytics:install')->assertSuccessful();

        expect(File::exists($base.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'analytics.php'))->toBeTrue()
            ->and(File::get($base.DIRECTORY_SEPARATOR.'.env'))->toContain('# --- Falcon Analytics')
            ->and(File::get($base.DIRECTORY_SEPARATOR.'.env'))->toContain('ANALYTICS_ENABLED=true')
            ->and(File::get($base.DIRECTORY_SEPARATOR.'.env.example'))->toContain('ANALYTICS_GSC_CLIENT_ID=');
    } finally {
        File::deleteDirectory($base);
    }
});
