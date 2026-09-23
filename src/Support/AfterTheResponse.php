<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Closure;

use function Illuminate\Support\defer;

/**
 * Work that runs once the response has gone, and finishes even when the
 * connection closes before PHP is done writing.
 *
 * @internal
 */
final class AfterTheResponse
{
    /**
     * Let the current request finish its work whatever happens to the
     * connection · called before the response is sent.
     */
    public static function keepRunning(): void
    {
        ignore_user_abort(true);
    }

    /**
     * Run the work after the response, and keep the request alive until it is
     * done.
     */
    public static function run(Closure $work): void
    {
        self::keepRunning();

        defer($work);
    }
}
