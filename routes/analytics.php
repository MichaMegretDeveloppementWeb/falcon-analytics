<?php

declare(strict_types=1);

use Falcon\Analytics\Http\Controllers\Dashboard\EventsController;
use Falcon\Analytics\Http\Controllers\Dashboard\FunnelsController;
use Falcon\Analytics\Http\Controllers\Dashboard\IntegrationsController;
use Falcon\Analytics\Http\Controllers\Dashboard\OverviewController;
use Falcon\Analytics\Http\Controllers\Dashboard\RealtimeController;
use Falcon\Analytics\Http\Controllers\Dashboard\SessionDetailController;
use Falcon\Analytics\Http\Controllers\Dashboard\SessionsController;
use Falcon\Analytics\Http\Controllers\Dashboard\VisitorDetailController;
use Falcon\Analytics\Http\Controllers\Dashboard\VisitorsController;
use Falcon\Analytics\Http\Controllers\IngestController;
use Falcon\Analytics\Http\Controllers\Marketing\AdDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\AdsController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignsController;
use Falcon\Analytics\Http\Controllers\Marketing\MarketingDashboardController;
use Falcon\Analytics\Http\Controllers\SearchConsoleCallbackController;
use Falcon\Analytics\Http\Controllers\SearchConsoleConnectController;
use Falcon\Analytics\Http\Middleware\EnsureAnalyticsAccepts;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Ingestion endpoint. A minimal stack (cookies + session) without CSRF: the
// beacon cannot carry a token, so forged requests are stopped by the origin
// check and rate limit inside EnsureAnalyticsAccepts / throttle instead.
Route::post('/'.ltrim((string) config('analytics.endpoint'), '/'), IngestController::class)
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        EnsureAnalyticsAccepts::class,
        'throttle:'.config('analytics.throttle'),
    ])
    ->name('analytics.ingest');

// Le collecteur etait servi ici, par une route, avec un an de cache et une
// empreinte dans l'adresse. L'hote l'importe desormais dans son entree
// JavaScript publique, et c'est son build qui le nomme, le versionne et le
// sert. Retire le 2026-09-06.

// Dashboard. Fully configurable mounting (prefix, route-name prefix, middleware)
// so it drops into any host: the defaults suit a single-guard app, and the
// middleware must include a session stack (e.g. 'web') since package routes are
// registered outside the host's route groups.
$dashboard = config('analytics.dashboard');

// An explicitly empty middleware list mounts the module without any protection
// (no session, no auth): almost certainly a host misconfiguration, so say it.
if (($dashboard['middleware'] ?? null) === []) {
    Log::channel(config('analytics.log_channel'))
        ->warning('Analytics dashboard mounted with an empty middleware list: the screens are publicly reachable.');
}

Route::prefix((string) ($dashboard['route_prefix'] ?? 'admin/analytics'))
    ->middleware($dashboard['middleware'] ?? ['web', 'auth'])
    ->name(($dashboard['route_name'] ?? 'analytics').'.')
    ->group(function (): void {
        Route::get('/', OverviewController::class)->name('overview');
        Route::get('/realtime', RealtimeController::class)->name('realtime');
        Route::get('/visitors', VisitorsController::class)->name('visitors');
        Route::get('/visitors/{visitor}', VisitorDetailController::class)->name('visitors.show');
        Route::get('/events', EventsController::class)->name('events');
        Route::get('/funnels', FunnelsController::class)->name('funnels');
        Route::get('/sessions', SessionsController::class)->name('sessions');
        Route::get('/sessions/{session}', SessionDetailController::class)->name('sessions.show');

        // Integrations (Google Search Console): the page plus the two OAuth
        // legs, all behind the same admin middleware as the dashboard.
        Route::get('/integrations', IntegrationsController::class)->name('integrations');
        Route::get('/integrations/search-console/connect', SearchConsoleConnectController::class)->name('integrations.search-console.connect');
        Route::get('/integrations/search-console/callback', SearchConsoleCallbackController::class)->name('integrations.search-console.callback');
    });

// Marketing. A separate top-level module (its own prefix, route names and menu),
// mounted like the dashboard from its own config block.
$marketing = config('analytics.marketing');

if (($marketing['middleware'] ?? null) === []) {
    Log::channel(config('analytics.log_channel'))
        ->warning('Analytics marketing module mounted with an empty middleware list: the screens are publicly reachable.');
}

Route::prefix((string) ($marketing['route_prefix'] ?? 'admin/marketing'))
    ->middleware($marketing['middleware'] ?? ['web', 'auth'])
    ->name(($marketing['route_name'] ?? 'marketing').'.')
    ->group(function (): void {
        Route::get('/', MarketingDashboardController::class)->name('dashboard');
        Route::get('/campaigns', CampaignsController::class)->name('campaigns');
        Route::get('/campaigns/{campaign}', CampaignDetailController::class)->name('campaigns.show');
        Route::get('/ads', AdsController::class)->name('ads');
        Route::get('/ads/{ad}', AdDetailController::class)->name('ads.show');
    });
