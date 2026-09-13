<?php

declare(strict_types=1);

use Falcon\Analytics\Http\Controllers\IngestController;
use Falcon\Analytics\Http\Middleware\EnsureAnalyticsAccepts;
use Illuminate\Support\Facades\Route;

/*
 * The public area: the collector posts here, and nothing else lives at this
 * level. The route name is fixed — a host changes the address, never the name.
 *
 * The stack carries no CSRF on purpose: a beacon cannot hold a token. The
 * origin check inside EnsureAnalyticsAccepts and the rate limit stand in its
 * place, and **neither can be removed** — they are appended here, after
 * whatever session stack the host has chosen, so emptying `web.middleware`
 * takes away the session and leaves both of these standing.
 *
 * **Not removable is not the same as not adjustable**, and saying the second
 * was wrong: the rate limit reads `analytics.throttle` on the line below, and
 * a host that measures a busy public page is meant to raise it. It is the
 * origin check that has no setting at all.
 */
Route::post('/'.ltrim((string) config('analytics.endpoint'), '/'), IngestController::class)
    ->middleware([
        ...(array) config('analytics.web.middleware', []),
        EnsureAnalyticsAccepts::class,
        'throttle:'.config('analytics.throttle'),
    ])
    ->name('analytics.web.ingest');
