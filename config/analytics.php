<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, no event is ingested and the collector is not rendered.
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | Channel for the package's own logs (ingestion and download errors). An
    | empty value falls back to the application's default channel.
    |
    */

    'log_channel' => env('ANALYTICS_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Ingestion endpoint
    |--------------------------------------------------------------------------
    |
    | Path the collector posts batches to, its rate limit (requests,minutes),
    | and IPs/CIDRs excluded entirely from tracking (internal staff, monitors).
    |
    */

    'endpoint' => env('ANALYTICS_ENDPOINT', '__analytics'),

    'throttle' => env('ANALYTICS_THROTTLE', '120,1'),

    'exclude_ips' => [],

    /*
    |--------------------------------------------------------------------------
    | Host integration callbacks
    |--------------------------------------------------------------------------
    |
    | The subject resolver, consent check and exclusion rule are closures and
    | therefore CANNOT live in this file (config:cache forbids closures). They
    | are registered on the Analytics manager from a service provider:
    |
    |   Analytics::resolveSubjectUsing(fn () => ...);   // ?array{type,id}
    |   Analytics::consentUsing(fn () => ...);          // bool, persistent id
    |   Analytics::excludeUsing(fn () => ...);          // bool, drop the request
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        'route_prefix' => env('ANALYTICS_ROUTE_PREFIX', 'admin/analytics'),
        'middleware' => ['web'],
        // null renders the dashboard inside the package layout; set a host
        // layout name (e.g. 'layouts.admin') to nest it in the host chrome.
        'layout' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data lifecycle
    |--------------------------------------------------------------------------
    |
    | Raw events are pruned past retention_days. Aggregates are kept forever.
    |
    */

    'retention_days' => 90,

    'session' => [
        // A session is considered ended after this much inactivity. The stored
        // ended_at is last_activity_at + timeout_minutes, never the sweep time.
        'timeout_minutes' => 5,
        // Visibility-gated heartbeat interval that keeps last_activity_at fresh.
        'heartbeat_seconds' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    */

    'privacy' => [
        // Raw IP is stored by default (locality + connection history). Set true
        // to store a truncated/anonymised IP instead.
        'anonymize_ip' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation (local database, no third-party call)
    |--------------------------------------------------------------------------
    */

    'geoip' => [
        // Local City database used to resolve localities. An empty env value
        // falls back to the storage path where analytics:geoip:download writes.
        'database_path' => env('ANALYTICS_GEOIP_DATABASE') ?: storage_path('app/analytics/dbip-city.mmdb'),

        // Free DB-IP City Lite source ({month} is replaced with YYYY-MM).
        'download_url' => env('ANALYTICS_GEOIP_URL', 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz'),
    ],

];
