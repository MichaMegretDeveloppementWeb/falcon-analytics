<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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
