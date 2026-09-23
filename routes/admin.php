<?php

declare(strict_types=1);

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Http\Controllers\Dashboard\EventsController;
use Falcon\Analytics\Http\Controllers\Dashboard\FunnelsController;
use Falcon\Analytics\Http\Controllers\Dashboard\IntegrationsController;
use Falcon\Analytics\Http\Controllers\Dashboard\OverviewController;
use Falcon\Analytics\Http\Controllers\Dashboard\RealtimeController;
use Falcon\Analytics\Http\Controllers\Dashboard\SessionDetailController;
use Falcon\Analytics\Http\Controllers\Dashboard\SessionsController;
use Falcon\Analytics\Http\Controllers\Dashboard\VisitorDetailController;
use Falcon\Analytics\Http\Controllers\Dashboard\VisitorsController;
use Falcon\Analytics\Http\Controllers\SearchConsoleCallbackController;
use Falcon\Analytics\Http\Controllers\SearchConsoleConnectController;
use Illuminate\Support\Facades\Route;

/*
 * The analytics screens of the administration area · the list, and nothing but
 * the list.
 *
 * Their address, their middleware and their name prefix come from the group the
 * provider opens around this file. One file tells every address and every guard
 * of the package, and it is that one.
 *
 * The route NAMES are fixed. A host reads them in its menu and its redirects; a
 * name that moved with the configuration could not be written down anywhere.
 *
 * Each address names the ability it asks. The provider adds, after it, the
 * middleware a host lays on that ability's branch.
 */

Route::get('/', OverviewController::class)->name('overview')->can(Ability::Overview);
Route::get('/realtime', RealtimeController::class)->name('realtime')->can(Ability::Realtime);
Route::get('/visitors', VisitorsController::class)->name('visitors')->can(Ability::Visitors);
Route::get('/visitors/{visitor}', VisitorDetailController::class)->name('visitors.show')->can(Ability::Visitors);
Route::get('/events', EventsController::class)->name('events')->can(Ability::Events);
Route::get('/funnels', FunnelsController::class)->name('funnels')->can(Ability::Funnels);
Route::get('/sessions', SessionsController::class)->name('sessions')->can(Ability::Sessions);
Route::get('/sessions/{session}', SessionDetailController::class)->name('sessions.show')->can(Ability::Sessions);

// Integrations (Google Search Console): the page plus the two OAuth legs, all
// behind the same middleware as the screens.
Route::get('/integrations', IntegrationsController::class)->name('integrations')->can(Ability::Integrations);
Route::get('/integrations/search-console/connect', SearchConsoleConnectController::class)
    ->name('integrations.search-console.connect')
    ->can(Ability::IntegrationsManage);
Route::get('/integrations/search-console/callback', SearchConsoleCallbackController::class)
    ->name('integrations.search-console.callback')
    ->can(Ability::IntegrationsManage);
