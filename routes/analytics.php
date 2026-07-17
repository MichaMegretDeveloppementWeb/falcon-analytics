<?php

declare(strict_types=1);

use Falcon\Analytics\Http\Controllers\CollectorScriptController;
use Falcon\Analytics\Http\Controllers\IngestController;
use Falcon\Analytics\Http\Middleware\EnsureAnalyticsAccepts;
use Falcon\Analytics\Livewire\Dashboard\AdDetailPage;
use Falcon\Analytics\Livewire\Dashboard\AdsPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignsPage;
use Falcon\Analytics\Livewire\Dashboard\EventsPage;
use Falcon\Analytics\Livewire\Dashboard\FunnelsPage;
use Falcon\Analytics\Livewire\Dashboard\MarketingDashboardPage;
use Falcon\Analytics\Livewire\Dashboard\OverviewPage;
use Falcon\Analytics\Livewire\Dashboard\RealtimePage;
use Falcon\Analytics\Livewire\Dashboard\SessionDetailPage;
use Falcon\Analytics\Livewire\Dashboard\SessionsPage;
use Falcon\Analytics\Livewire\Dashboard\VisitorDetailPage;
use Falcon\Analytics\Livewire\Dashboard\VisitorsPage;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
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

// Cached collector script, served as a static asset (no session or cookies).
Route::get('/'.ltrim((string) config('analytics.endpoint'), '/').'.js', CollectorScriptController::class)
    ->name('analytics.script');

// Dashboard. Fully configurable mounting (prefix, route-name prefix, middleware)
// so it drops into any host: the defaults suit a single-guard app, and the
// middleware must include a session stack (e.g. 'web') since package routes are
// registered outside the host's route groups.
$dashboard = config('analytics.dashboard');

Route::prefix((string) ($dashboard['route_prefix'] ?? 'admin/analytics'))
    ->middleware($dashboard['middleware'] ?? ['web', 'auth'])
    ->name(($dashboard['route_name'] ?? 'analytics').'.')
    ->group(function (): void {
        Route::livewire('/', OverviewPage::class)->name('overview');
        Route::livewire('/realtime', RealtimePage::class)->name('realtime');
        Route::livewire('/visitors', VisitorsPage::class)->name('visitors');
        Route::livewire('/visitors/{visitor}', VisitorDetailPage::class)->name('visitors.show');
        Route::livewire('/events', EventsPage::class)->name('events');
        Route::livewire('/funnels', FunnelsPage::class)->name('funnels');
        Route::livewire('/sessions', SessionsPage::class)->name('sessions');
        Route::livewire('/sessions/{session}', SessionDetailPage::class)->name('sessions.show');
    });

// Marketing. A separate top-level module (its own prefix, route names and menu),
// mounted like the dashboard from its own config block.
$marketing = config('analytics.marketing');

Route::prefix((string) ($marketing['route_prefix'] ?? 'admin/marketing'))
    ->middleware($marketing['middleware'] ?? ['web', 'auth'])
    ->name(($marketing['route_name'] ?? 'marketing').'.')
    ->group(function (): void {
        Route::livewire('/', MarketingDashboardPage::class)->name('dashboard');
        Route::livewire('/campaigns', CampaignsPage::class)->name('campaigns');
        Route::livewire('/campaigns/{campaign}', CampaignDetailPage::class)->name('campaigns.show');
        Route::livewire('/ads', AdsPage::class)->name('ads');
        Route::livewire('/ads/{ad}', AdDetailPage::class)->name('ads.show');
    });
