<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google's OAuth return leg: verifies the anti-CSRF state, exchanges the code
 * for tokens and lands back on the integrations page, which then offers the
 * property choice. Every failure degrades to a flashed error on that page;
 * this endpoint never renders anything itself.
 */
final class SearchConsoleCallbackController
{
    public function __invoke(Request $request, SearchConsoleAuth $auth): RedirectResponse
    {
        $routeName = (string) config('analytics.dashboard.route_name', 'analytics');
        $expected = (string) $request->session()->pull('analytics.search_console.state', '');
        $received = (string) $request->query('state', '');

        if ($expected === '' || ! hash_equals($expected, $received)) {
            return $this->fail($routeName, __('La connexion a expiré ou la requête est invalide. Réessayez.'));
        }

        if ($request->query('error') !== null || (string) $request->query('code', '') === '') {
            return $this->fail($routeName, __('L\'autorisation Google a été refusée ou annulée.'));
        }

        try {
            $auth->exchangeCode((string) $request->query('code'));
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.exchange_failed', ['exception' => $e]);

            return $this->fail($routeName, __('L\'échange avec Google a échoué. Réessayez.'));
        }

        $request->session()->flash('analytics.search_console.flash', [
            'type' => 'success',
            'title' => __('Compte Google connecté. Choisissez la propriété à rattacher.'),
        ]);

        return redirect()->route($routeName.'.integrations');
    }

    private function fail(string $routeName, string $message): RedirectResponse
    {
        session()->flash('analytics.search_console.flash', ['type' => 'danger', 'title' => $message]);

        return redirect()->route($routeName.'.integrations');
    }
}
