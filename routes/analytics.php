<?php

declare(strict_types=1);

use Falcon\Analytics\Http\Controllers\CollectorScriptController;
use Falcon\Analytics\Http\Controllers\IngestController;
use Falcon\Analytics\Http\Middleware\EnsureAnalyticsAccepts;
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
