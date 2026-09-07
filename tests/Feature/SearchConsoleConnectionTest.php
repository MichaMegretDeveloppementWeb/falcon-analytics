<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Exceptions\SearchConsoleException;
use Falcon\Analytics\Livewire\Dashboard\IntegrationsPage;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

final class SearchConsoleConnectionTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = TestAdmin::create([]);
    }

    private function configureCredentials(): void
    {
        config()->set('analytics.search_console.client_id', 'client-id-123');
        config()->set('analytics.search_console.client_secret', 'secret-456');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function connection(array $attributes = []): SearchConsoleConnection
    {
        return SearchConsoleConnection::query()->create(array_merge([
            'refresh_token' => 'refresh-token-plain',
            'access_token' => 'access-token-plain',
            'token_expires_at' => now()->addHour(),
            'status' => SearchConsoleConnection::STATUS_CONNECTED,
            'property' => 'sc-domain:example.com',
        ], $attributes));
    }

    public function test_it_reports_the_feature_unconfigured_until_both_oauth_credentials_are_set(): void
    {
        $this->assertFalse(app(SearchConsoleAuth::class)->configured());

        config()->set('analytics.search_console.client_id', 'client-id-123');
        $this->assertFalse(app(SearchConsoleAuth::class)->configured());

        config()->set('analytics.search_console.client_secret', 'secret-456');
        $this->assertTrue(app(SearchConsoleAuth::class)->configured());
    }

    public function test_it_redirects_the_connect_route_to_google_with_the_expected_oauth_parameters(): void
    {
        $this->configureCredentials();
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('analytics.integrations.search-console.connect'));

        $state = session('analytics.search_console.state');

        $this->assertIsString($state);
        $this->assertNotSame('', $state);

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $location);
        $this->assertSame('client-id-123', $query['client_id']);
        $this->assertSame('https://www.googleapis.com/auth/webmasters.readonly', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame($state, $query['state']);
        $this->assertSame(route('analytics.integrations.search-console.callback'), $query['redirect_uri']);
    }

    public function test_it_sends_the_connect_route_back_to_the_integrations_page_when_unconfigured(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.integrations.search-console.connect'))
            ->assertRedirect(route('analytics.integrations'));
    }

    public function test_it_rejects_a_callback_whose_state_does_not_match_the_session(): void
    {
        $this->configureCredentials();
        $this->actingAs($this->admin, 'admin');

        $this->withSession(['analytics.search_console.state' => 'expected-state'])
            ->get(route('analytics.integrations.search-console.callback', ['state' => 'forged', 'code' => 'abc']))
            ->assertRedirect(route('analytics.integrations'));

        $this->assertSame(0, SearchConsoleConnection::query()->count());
    }

    public function test_it_rejects_a_denied_or_codeless_callback_without_storing_anything(): void
    {
        $this->configureCredentials();
        $this->actingAs($this->admin, 'admin');

        $this->withSession(['analytics.search_console.state' => 'state-1'])
            ->get(route('analytics.integrations.search-console.callback', ['state' => 'state-1', 'error' => 'access_denied']))
            ->assertRedirect(route('analytics.integrations'));

        $this->assertSame(0, SearchConsoleConnection::query()->count());
    }

    public function test_it_exchanges_the_code_on_a_valid_callback_and_stores_an_encrypted_pending_connection(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.access',
                'refresh_token' => '1//refresh',
                'expires_in' => 3599,
            ]),
        ]);

        $this->actingAs($this->admin, 'admin');

        $this->withSession(['analytics.search_console.state' => 'state-1'])
            ->get(route('analytics.integrations.search-console.callback', ['state' => 'state-1', 'code' => 'auth-code']))
            ->assertRedirect(route('analytics.integrations'));

        $connection = SearchConsoleConnection::current();

        $this->assertNotNull($connection);
        $this->assertSame(SearchConsoleConnection::STATUS_PENDING_PROPERTY, $connection->status);
        $this->assertNull($connection->property);
        $this->assertSame('1//refresh', $connection->refresh_token);

        $raw = (string) DB::table('falcon_analytics_search_console')->value('refresh_token');
        $this->assertStringNotContainsString('1//refresh', $raw);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'auth-code');
    }

    public function test_it_replaces_a_previous_connection_when_a_new_callback_succeeds(): void
    {
        $this->configureCredentials();
        $this->connection();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r2', 'expires_in' => 3599]),
        ]);

        $this->actingAs($this->admin, 'admin');

        $this->withSession(['analytics.search_console.state' => 's'])
            ->get(route('analytics.integrations.search-console.callback', ['state' => 's', 'code' => 'c']));

        $this->assertSame(1, SearchConsoleConnection::query()->count());
        $this->assertSame('r2', SearchConsoleConnection::current()->refresh_token);
    }

    public function test_it_reuses_a_fresh_cached_access_token_without_calling_google(): void
    {
        $this->configureCredentials();
        Http::fake();
        $connection = $this->connection(['token_expires_at' => now()->addMinutes(30)]);

        $this->assertSame('access-token-plain', app(SearchConsoleAuth::class)->accessTokenFor($connection));
        Http::assertNothingSent();
    }

    public function test_it_refreshes_an_expired_access_token_transparently_and_persists_it(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        ]);

        $connection = $this->connection(['token_expires_at' => now()->subMinute()]);

        $this->assertSame('fresh-token', app(SearchConsoleAuth::class)->accessTokenFor($connection));
        $this->assertSame('fresh-token', $connection->refresh()->access_token);

        Http::assertSent(fn ($request): bool => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-token-plain');
    }

    public function test_it_flags_the_connection_when_the_refresh_is_rejected(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $connection = $this->connection(['token_expires_at' => now()->subMinute()]);

        $this->assertThrows(
            fn () => app(SearchConsoleAuth::class)->accessTokenFor($connection),
            SearchConsoleException::class,
        );

        $this->assertSame(SearchConsoleConnection::STATUS_ERROR, $connection->refresh()->status);
        $this->assertStringContainsString('invalid_grant', $connection->last_error);
    }

    public function test_it_renders_the_unconfigured_card_with_the_env_keys_to_provide(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.integrations'))
            ->assertSuccessful()
            ->assertSeeText(__('Intégrations'))
            ->assertSeeText('ANALYTICS_GSC_CLIENT_ID');
    }

    public function test_it_renders_the_connect_button_when_configured_and_disconnected(): void
    {
        $this->configureCredentials();
        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.integrations'))
            ->assertSuccessful()
            ->assertSeeText(__('Connecter Google Search Console'));
    }

    public function test_it_lists_the_verified_properties_while_the_connection_awaits_its_property(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'https://unverified.example/', 'permissionLevel' => 'siteUnverifiedUser'],
            ]]),
        ]);

        $this->connection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)
            ->assertSeeText('sc-domain:example.com')
            ->assertDontSeeText('unverified.example')
            ->assertSeeText(__('Rattacher'));
    }

    public function test_it_attaches_a_listed_property_and_marks_the_connection_connected(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
            ]]),
        ]);

        $this->connection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)
            ->call('selectProperty', 'sc-domain:example.com')
            ->assertDispatched('toast');

        $connection = SearchConsoleConnection::current();

        $this->assertSame(SearchConsoleConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('sc-domain:example.com', $connection->property);
    }

    public function test_it_refuses_to_attach_a_property_google_did_not_list(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []]),
        ]);

        $this->connection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)->call('selectProperty', 'sc-domain:forged.com');

        $this->assertSame(
            SearchConsoleConnection::STATUS_PENDING_PROPERTY,
            SearchConsoleConnection::current()->status,
        );
    }

    public function test_it_shows_the_connected_state_with_the_attached_property_and_the_manual_sync_button(): void
    {
        $this->configureCredentials();
        $this->connection();
        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.integrations'))
            ->assertSuccessful()
            ->assertSeeText('sc-domain:example.com')
            ->assertSeeText(__('Connectée'))
            ->assertSeeText(__('Jamais'))
            ->assertSeeText(__('Synchroniser maintenant'));
    }

    public function test_it_syncs_on_demand_from_the_integrations_page_same_path_as_the_command(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
                ['keys' => ['2026-07-18', 'location voiture'], 'clicks' => 7, 'impressions' => 210, 'position' => 4.2],
            ]]),
        ]);

        $connection = $this->connection(['last_synced_at' => null]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)
            ->call('syncNow')
            ->assertDispatched('toast');

        $this->assertSame(1, SearchQuery::query()->count());
        $this->assertNotNull($connection->refresh()->last_synced_at);
    }

    public function test_it_surfaces_a_manual_sync_failure_and_flags_the_connection(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => 'quota'], 429),
        ]);

        $connection = $this->connection();
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)
            ->call('syncNow')
            ->assertDispatched('toast');

        $this->assertSame(SearchConsoleConnection::STATUS_ERROR, $connection->refresh()->status);
    }

    public function test_it_ignores_a_manual_sync_without_an_attached_connection(): void
    {
        $this->configureCredentials();
        Http::fake();
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)->call('syncNow');

        Http::assertNothingSent();
    }

    public function test_it_disconnects_through_the_confirmation_modal_revoking_the_token_and_deleting_the_row(): void
    {
        $this->configureCredentials();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);
        $this->connection();
        $this->actingAs($this->admin, 'admin');

        Livewire::test(IntegrationsPage::class)
            ->call('confirmDisconnect')
            ->assertSet('modal', 'disconnect')
            ->call('disconnectConfirmed')
            ->assertSet('modal', '')
            ->assertDispatched('toast');

        $this->assertSame(0, SearchConsoleConnection::query()->count());
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://oauth2.googleapis.com/revoke'));
    }

    public function test_it_renders_a_graceful_error_state_instead_of_a_500_when_the_integrations_read_fails(): void
    {
        $this->configureCredentials();
        $this->actingAs($this->admin, 'admin');

        // On force une vraie lecture en échec · la recherche de connexion tombe
        // sur une table absente, au montage comme au rendu gardé.
        $this->withoutTable('falcon_analytics_search_console', function (): void {
            Livewire::test(IntegrationsPage::class)->assertSee(__('Données indisponibles'));
        });
    }

    public function test_it_redirects_a_guest_away_from_the_integrations_page_and_oauth_routes(): void
    {
        $this->configureCredentials();

        $this->get(route('analytics.integrations'))->assertRedirect(route('login'));
        $this->get(route('analytics.integrations.search-console.connect'))->assertRedirect(route('login'));
        $this->get(route('analytics.integrations.search-console.callback'))->assertRedirect(route('login'));
    }
}
