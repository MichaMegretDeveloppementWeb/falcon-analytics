<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Fixtures\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A host's own guard, known to the router by an alias of its own. */
final class EnsureTheHostAllows
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
