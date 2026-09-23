<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Fixtures\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A host's own step · it sends every visitor elsewhere first, like a password confirmation. */
final class AsksForAStep
{
    public function handle(Request $request, Closure $next): Response
    {
        return redirect('/step');
    }
}
