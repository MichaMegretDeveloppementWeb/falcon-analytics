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
        'database_path' => env('ANALYTICS_GEOIP_DATABASE'),
    ],

];
