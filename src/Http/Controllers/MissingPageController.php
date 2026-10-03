<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

/**
 * Answers an address under one of the package's prefixes that leads nowhere.
 *
 * It only raises: the page itself is drawn by `Support\MissingPage`, which
 * answers a row missing under a package route the same way.
 *
 * @internal
 */
final readonly class MissingPageController
{
    public function __invoke(): never
    {
        abort(404);
    }
}
