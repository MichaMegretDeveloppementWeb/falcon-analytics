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
    | Assets
    |--------------------------------------------------------------------------
    |
    | Vos entrées, où `analytics:install` a écrit ses imports.
    |
    | Le paquet ne compile rien : ces fichiers sont les vôtres, et c'est votre
    | `npm run build` qui les lit. Deux lignes seulement — la feuille des
    | tableaux de bord dans `admin_css`, le collecteur dans `web_js`.
    |
    | À modifier quand vous déplacez ou renommez un de ces fichiers :
    |
    |     'admin_css' => 'resources/css/back-office/index.css',
    |
    | Chemins relatifs à la racine du projet.
    |
    | `admin_css` reçoit la feuille des tableaux de bord, `web_js` le collecteur.
    | `admin_js` ne porte aucun import du paquet : c'est ce qu'il passe à
    | `ui-kit:install`, le kit dessinant ses écrans.
    |
    | Un `web_css` a été retenu ici jusqu'au 2026-09-07, sans être lu par
    | personne : le paquet n'a aucun CSS public à importer.
    |
    */

    'assets' => [
        'admin_css' => 'resources/css/app.css',
        'admin_js' => 'resources/js/app.js',
        'web_js' => 'resources/js/app.js',
    ],

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
    | Funnels & tracked events
    |--------------------------------------------------------------------------
    |
    | Paths to the code-declared funnels and tracked-events files, loaded lazily.
    | Null falls back to app/Analytics/funnels.php and app/Analytics/events.php in
    | the host. The events file is the single source of truth for the events
    | offered as conversion objectives (name + label + optional value); keep it in
    | sync with the code via `php artisan analytics:events:scan`.
    |
    */

    'funnels_path' => null,

    'events_path' => null,

    // Paths (relative to the base path) scanned by analytics:events:scan for
    // data-track-event attributes and Analytics::record calls.
    'events_scan_paths' => ['app', 'resources/views'],

    /*
    |--------------------------------------------------------------------------
    | Marketing module
    |--------------------------------------------------------------------------
    |
    | The marketing screens (dashboard, campaigns, ads) mount as their own
    | top-level module, separate from the analytics dashboard, mirroring the
    | dashboard block below. A campaign or ad is matched to a session by the free
    | URL-parameter conditions captured on it (mkt_params), at report time.
    |
    */

    'marketing' => [
        'route_prefix' => 'admin/marketing',
        'route_name' => 'marketing',
        'middleware' => ['web', 'auth'],
        'layout' => null,
        'layout_section' => 'content',
    ],

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
    | declarative values, so a host only needs @analyticsConfig and these
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

        // Blade layout the screens extend. null uses the package's own shell; set
        // a host layout name (e.g. 'layouts.admin') to nest it in the host chrome.
        'layout' => null,

        // The section that layout yields the screen into. Only matters when the
        // host layout names it something other than 'content'.
        'layout_section' => 'content',
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
        // How often the collector flushes its buffered event batch to the server.
        'flush_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime screen
    |--------------------------------------------------------------------------
    |
    | The realtime page refreshes through plain Livewire polling (no worker,
    | websocket or external service), suspended while the tab is hidden.
    |
    */

    'realtime' => [
        // Refresh interval of the realtime page.
        'poll_seconds' => 10,

        // A session is "online now" when its last activity is within this
        // window (the collector heartbeats every heartbeat_seconds).
        'online_seconds' => 60,

        // The "recent" window every realtime block reads (KPIs, feed, charts).
        'window_minutes' => 30,

        // Hard bound of the activity feed, so a tick never grows with traffic.
        'feed_limit' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Search Console (organic search queries)
    |--------------------------------------------------------------------------
    |
    | Google strips the search query from referrers, so organic keywords are
    | only available through the Search Console API, authorised by the admin
    | via OAuth (read-only). The host provides a Google Cloud OAuth 2.0 web
    | client with the Search Console API enabled; while the credentials are
    | empty the whole feature stays hidden. The redirect URI defaults to the
    | package callback route and must be registered on the OAuth client.
    |
    */

    'search_console' => [
        'client_id' => env('ANALYTICS_GSC_CLIENT_ID', ''),
        'client_secret' => env('ANALYTICS_GSC_CLIENT_SECRET', ''),

        // Absolute redirect URI registered on the OAuth client. Null uses the
        // package callback route ({dashboard.route_prefix}/integrations/search-console/callback).
        'redirect' => env('ANALYTICS_GSC_REDIRECT'),
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
    |
    | MaxMind GeoLite2 City: free, accurate and fully local, so a visitor's IP
    | never leaves the server. Get a free licence key at
    | https://www.maxmind.com/en/geolite2/signup, then run analytics:geoip:download.
    |
    */

    'geoip' => [
        'license_key' => env('ANALYTICS_GEOIP_LICENSE_KEY', ''),
        'edition' => env('ANALYTICS_GEOIP_EDITION', 'GeoLite2-City'),

        // Where analytics:geoip:download writes the extracted .mmdb.
        'database_path' => env('ANALYTICS_GEOIP_DATABASE') ?: storage_path('app/analytics/GeoLite2-City.mmdb'),

        // Local development: public IP substituted for private/reserved request
        // IPs (127.0.0.1 can never be located). Inert in production by design,
        // since real public IPs are never overridden.
        'dev_ip' => env('ANALYTICS_GEOIP_DEV_IP'),

        // MaxMind permalink ({edition} and {license_key} are substituted).
        'download_url' => 'https://download.maxmind.com/app/geoip_download?edition_id={edition}&license_key={license_key}&suffix=tar.gz',
    ],

];
