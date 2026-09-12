<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

/*
 * What the provider declares to Laravel, declared to the analyser.
 *
 * larastan checks that a `view('...')` names a view that exists, and it does so
 * by actually calling `view()->exists()`. In a package, the `analytics::` prefix
 * is only registered when the provider boots, which the analysis does not run.
 *
 * The registration is the same as in AnalyticsServiceProvider::boot(). Without
 * it the check is inapplicable to a package and would have to be silenced,
 * which would amount to no longer checking the views at all.
 */
try {
    View::addNamespace('analytics', __DIR__.'/resources/views');
} catch (Throwable) {
    // The analysis can run without a booted container. The view check is then
    // inoperative, which is the state it was in before: nothing worse.
}
