<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The package's own settings — NOT the host's
|--------------------------------------------------------------------------
|
| This file is never published, and nothing in it is ever asked of anybody.
| These are the package's own values, kept in one file so they read in one
| place, change in one line, and can be overridden in a test.
|
| The difference with `analytics.php` holds in one question: would two
| reasonable hosts answer differently? If yes, it is a host setting and it goes
| in the published file. If no, it is the package's own choice and it comes
| here — because a value that suits nobody is a defect to fix in the package,
| not a question to put to every project.
|
| The service provider lays these AFTER the host's configuration, with no
| regard for what a published copy would say. A key of this file copied into
| `config/analytics.php` therefore has no effect.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Maintenance, caught up on a screen load
    |--------------------------------------------------------------------------
    |
    | The scheduler is the ordinary route. This one exists because a shared
    | hosting scheduler stops without a word: the summarising stops with it, and
    | the erasing too, so nothing is lost, but the summaries fall behind and no
    | screen says so.
    |
    | None of this is a question for the host: it is for the package to keep its
    | own books, and to do it without being felt.
    |
    */

    'maintenance' => [
        // The catching up happens after the page has been sent, so the screen
        // never slows down. Switched off, a stopped scheduler leaves the
        // summaries behind until it runs again.
        'on_screen_load' => true,

        // Never more than once an hour, whatever the number of open pages and
        // the number of administrators.
        'interval_minutes' => 60,

        // Days summarised per visit, so a long backlog is caught up over several
        // visits instead of holding a server process long after the page has left.
        'days_per_run' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | What a page still sends once its user has signed out
    |--------------------------------------------------------------------------
    |
    | A click sent on the way out arrives after the host's sign-out has emptied
    | the session. The page's sealed context still names who it was drawn for,
    | and the batch joins that person's session, but only this long after the
    | host's session last vouched for them, on the server's clock. Past it, the
    | batch is dropped, so a page left open does not keep a signed-out person's
    | session alive.
    |
    | Never shorter than one heartbeat plus one flush: a host that slows the
    | collector down would otherwise lose the sends of every sign-out.
    |
    */

    'collector' => [
        'context_grace_seconds' => 60,
    ],

];
