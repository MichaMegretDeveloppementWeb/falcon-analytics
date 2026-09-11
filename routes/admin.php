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
use Falcon\Analytics\Http\Controllers\Marketing\AdDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\AdsController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignDetailController;
use Falcon\Analytics\Http\Controllers\Marketing\CampaignsController;
use Falcon\Analytics\Http\Controllers\Marketing\MarketingDashboardController;
use Falcon\Analytics\Http\Controllers\SearchConsoleCallbackController;
use Falcon\Analytics\Http\Controllers\SearchConsoleConnectController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
 * The administration area, and the two entities it holds: the analytics
 * screens, and the marketing screens. Each mounts from its own config block —
 * address and middleware — so a host can hang them wherever it wants, and give
 * marketing another guard than analytics.
 *
 * The route NAMES are fixed. A host reads them in its menu and its redirects;
 * a name that moves with the configuration cannot be written down anywhere.
 *
 * The middleware must carry a session stack ('web', typically): package routes
 * are registered outside the host's own route groups, so they inherit nothing.
 */

$admin = config('analytics.admin');

// An explicitly empty middleware list mounts the screens without any protection
// (no session, no auth): almost certainly a host misconfiguration, so say it.
if (($admin['middleware'] ?? null) === []) {
    Log::channel(config('analytics.log_channel'))
        ->warning('Analytics screens mounted with an empty middleware list: they are publicly reachable.');
}

Route::prefix((string) ($admin['route_prefix'] ?? 'admin/analytics'))
    ->middleware($admin['middleware'] ?? ['web', 'auth'])
    ->name('analytics.admin.')
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
        // legs, all behind the same middleware as the screens.
        Route::get('/integrations', IntegrationsController::class)->name('integrations');
        Route::get('/integrations/search-console/connect', SearchConsoleConnectController::class)->name('integrations.search-console.connect');
        Route::get('/integrations/search-console/callback', SearchConsoleCallbackController::class)->name('integrations.search-console.callback');
    });

// Marketing: a second entity of the administration, with its own address and
// its own place in the menu, mounted from its own nested block.
$marketing = $admin['marketing'] ?? [];

if (($marketing['middleware'] ?? null) === []) {
    Log::channel(config('analytics.log_channel'))
        ->warning('Analytics marketing screens mounted with an empty middleware list: they are publicly reachable.');
}

Route::prefix((string) ($marketing['route_prefix'] ?? 'admin/marketing'))
    ->middleware($marketing['middleware'] ?? ['web', 'auth'])
    ->name('analytics.admin.marketing.')
    ->group(function (): void {
        Route::get('/', MarketingDashboardController::class)->name('dashboard');
        Route::get('/campaigns', CampaignsController::class)->name('campaigns');
        Route::get('/campaigns/{campaign}', CampaignDetailController::class)->name('campaigns.show');
        Route::get('/ads', AdsController::class)->name('ads');
        Route::get('/ads/{ad}', AdDetailController::class)->name('ads.show');
    });
