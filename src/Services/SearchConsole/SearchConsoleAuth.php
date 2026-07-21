<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\SearchConsole;

use Falcon\Analytics\Models\SearchConsoleConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Lightweight Google OAuth 2.0 client for the Search Console integration
 * (plain HTTP, no google/apiclient): authorization URL, code exchange,
 * transparent access-token refresh and best-effort revocation. Read-only
 * scope; the refresh token is obtained once (access_type=offline +
 * prompt=consent) and stored encrypted on the connection model.
 */
final class SearchConsoleAuth
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /** Refresh ahead of expiry so an in-flight request never hits a dead token. */
    private const EXPIRY_MARGIN_SECONDS = 60;

    /**
     * The whole feature is gated on the host providing OAuth credentials.
     */
    public function configured(): bool
    {
        return trim((string) config('analytics.search_console.client_id')) !== ''
            && trim((string) config('analytics.search_console.client_secret')) !== '';
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => (string) config('analytics.search_console.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code and store the connection (replacing any
     * previous one: the integration holds a single connection by design).
     */
    public function exchangeCode(string $code): SearchConsoleConnection
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => (string) config('analytics.search_console.client_id'),
            'client_secret' => (string) config('analytics.search_console.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google token exchange failed: '.((string) $response->json('error') ?: "HTTP {$response->status()}"));
        }

        $refreshToken = (string) $response->json('refresh_token');

        if ($refreshToken === '') {
            throw new RuntimeException('Google returned no refresh token.');
        }

        SearchConsoleConnection::query()->delete();

        return SearchConsoleConnection::query()->create([
            'refresh_token' => $refreshToken,
            'access_token' => (string) $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            'status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY,
        ]);
    }

    /**
     * A valid access token for the connection, refreshed transparently when
     * the cached one is expired (or about to). A rejected refresh (revoked
     * access) flags the connection so the dashboard can surface it.
     */
    public function accessTokenFor(SearchConsoleConnection $connection): string
    {
        $cached = $connection->access_token;

        if ($cached !== null && $cached !== '' && $connection->token_expires_at?->subSeconds(self::EXPIRY_MARGIN_SECONDS)->isFuture()) {
            return $cached;
        }

        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => (string) config('analytics.search_console.client_id'),
            'client_secret' => (string) config('analytics.search_console.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            $error = (string) $response->json('error') ?: "HTTP {$response->status()}";
            $connection->update(['status' => SearchConsoleConnection::STATUS_ERROR, 'last_error' => 'token_refresh: '.$error]);

            throw new RuntimeException('Google token refresh failed: '.$error);
        }

        $token = (string) $response->json('access_token');
        $connection->update([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $token;
    }

    /**
     * Best-effort revocation on disconnect: the row is deleted either way, so
     * a Google-side failure must never block the admin.
     */
    public function revoke(SearchConsoleConnection $connection): void
    {
        try {
            Http::asForm()->timeout(15)->post(self::REVOKE_URL, ['token' => $connection->refresh_token]);
        } catch (Throwable) {
            // The token dies with the deleted row; revocation is a courtesy.
        }
    }

    public function redirectUri(): string
    {
        $configured = (string) config('analytics.search_console.redirect');

        if ($configured !== '') {
            return $configured;
        }

        return route((string) config('analytics.dashboard.route_name', 'analytics').'.integrations.search-console.callback');
    }
}
