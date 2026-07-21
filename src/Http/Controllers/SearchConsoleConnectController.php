<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Starts the Google OAuth flow: stores a random anti-CSRF state in the admin
 * session and sends the browser to Google's consent screen. Inert (redirects
 * back) while the host has not configured the OAuth credentials.
 */
final class SearchConsoleConnectController
{
    public function __invoke(Request $request, SearchConsoleAuth $auth): RedirectResponse
    {
        $routeName = (string) config('analytics.dashboard.route_name', 'analytics');

        if (! $auth->configured()) {
            return redirect()->route($routeName.'.integrations');
        }

        $state = Str::random(40);
        $request->session()->put('analytics.search_console.state', $state);

        return redirect()->away($auth->authorizationUrl($state));
    }
}
