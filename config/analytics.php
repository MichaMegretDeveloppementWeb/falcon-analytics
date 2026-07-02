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

    'log_channel' => null,

    /*
    |--------------------------------------------------------------------------
    | Funnels
    |--------------------------------------------------------------------------
    |
    | Path to the code-declared funnels file, loaded lazily. Null falls back to
    | app/Analytics/funnels.php in the host.
    |
    */

    'funnels_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Ingestion endpoint
    |--------------------------------------------------------------------------
    |
    | Path the collector posts batches to, its rate limit (requests,minutes),
    | and IPs/CIDRs excluded entirely from tracking (internal staff, monitors).
    |
    */

    'endpoint' => '__analytics',

    'throttle' => '120,1',

    'exclude_ips' => [],

    /*
    |--------------------------------------------------------------------------
    | Host identity (zero-code integration)
    |--------------------------------------------------------------------------
    |
    | The package resolves the subject, exclusions and consent from these
    | declarative values, so a host only needs @analyticsScripts and these
    | settings. For advanced logic, register closures on the Analytics manager
    | from a service provider (they take precedence):
    |
    |   Analytics::resolveSubjectUsing(...); consentUsing(...); excludeUsing(...);
    |
    */

    'identity' => [
        // Guards whose authenticated user is the tracked subject (type = guard name).
        'subject_guards' => ['web'],

        // Guards whose authenticated user is excluded entirely (internal staff).
        'exclude_guards' => [],

        // Cookie whose value "1" grants the persistent visitor id (null = always session-scoped).
        'consent_cookie' => null,

        // Display metadata per subject guard, resolved at render time only (never
        // stored). For each guard: an optional 'label' shown instead of the guard
        // name, and a 'name' list of columns concatenated into a display name,
        // read from the guard's own model (derived from the auth config, or an
        // explicit 'model'/'table'/'key'). An optional 'fallback' list of columns
        // is used when the name columns are all empty.
        //   'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
        'subjects' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        // What appears in the URL for every dashboard page (e.g. /admin/analytics).
        'route_prefix' => 'admin/analytics',

        // Prefix for the route NAMES: 'analytics' -> route('analytics.overview').
        'route_name' => 'analytics',

        // Middleware protecting the dashboard. The default suits a single-guard
        // app; override to match the project (e.g. ['web', 'auth:admin']).
        'middleware' => ['web', 'auth'],

        // Blade layout the pages extend. null uses the package's own shell; set a
        // host layout name (e.g. 'layouts.admin') to nest it in the host chrome.
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

        // Query parameters (case-insensitive) redacted from stored URLs. Tracking
        // params (utm_*, gclid, fbclid, custom ad params) are kept; only likely
        // PII is removed. Set to [] to store URLs verbatim.
        'redact_query_params' => ['token', 'access_token', 'auth', 'password', 'secret', 'apikey', 'api_key', 'otp', 'signature', 'email'],
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
        'download_url' => 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz',
    ],

];
