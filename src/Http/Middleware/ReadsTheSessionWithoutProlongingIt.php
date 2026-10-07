<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Store;

/**
 * The host's session, opened for the collector without being prolonged.
 *
 * A send writes the session back, and hands its cookie back, only when it
 * changed something in it · a visitor identifier stored for the first time, or
 * a session the request had to renew. A heartbeat therefore no longer keeps a
 * signed-in user's session alive for ever, no longer ages the host's flash
 * data, and no longer overwrites what another request wrote meanwhile.
 *
 * @internal the package lays it in place of StartSession on the collector's
 *           route · a host keeps writing StartSession.
 */
final class ReadsTheSessionWithoutProlongingIt extends StartSession
{
    /**
     * @param  Session  $session
     * @return mixed
     */
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        $request->setLaravelSession($this->startSession($request, $session));

        $opened = [$session->getId(), $session->all()];

        $this->collectGarbage($session);

        $response = $next($request);

        if ([$session->getId(), $session->all()] !== $opened) {
            $this->keepTheFlashData($session);
            $this->addCookieToResponse($response, $session);
            $this->saveSession($request);
        }

        return $response;
    }

    /**
     * A send is never the page that shows a flash message · saving ages the
     * flash data, so it is flashed again first, and comes out as it went in.
     */
    private function keepTheFlashData(Session $session): void
    {
        if ($session instanceof Store) {
            $session->reflash();
        }
    }
}
