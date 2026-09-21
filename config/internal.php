<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The package's own settings — NOT the host's
|--------------------------------------------------------------------------
|
| This file is never published, and nothing in it is ever asked of anybody.
| These are design decisions of the package, laid here rather than inline so
| they read in one place, change in one line, and move for the length of a
| test.
|
| The difference with `analytics.php` holds in one question: would two
| reasonable hosts answer differently? If yes, it is a host setting and it goes
| in the published file. If no, it is the package's own choice and it comes
| here — because a value that suits nobody is a defect to fix in the package,
| not a question to put to every project.
|
| The service provider lays these AFTER the host's configuration, with no
| regard for what a published copy would say. A key of this file copied into
| `config/analytics.php` therefore has no effect, and that is deliberate.
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
    | the erasing too — nothing is lost, by design — but the summaries fall
    | behind and no screen says so.
    |
    | None of this is a question for the host: it is for the package to keep its
    | own books, and to do it without being felt.
    |
    */

    'maintenance' => [
        // The catching up happens after the page has been sent, so the screen
        // never slows down. Switched off, a dead scheduler stops showing.
        'on_screen_load' => true,

        // Never more than once an hour, whatever the number of open pages and
        // the number of administrators.
        'interval_minutes' => 60,

        // Days summarised per visit. Six months of backlog catches up in
        // twenty-six runs rather than one that would hold a server process long
        // after the page has left.
        'days_per_run' => 7,
    ],

];
