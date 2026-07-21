<?php

declare(strict_types=1);

use Falcon\Analytics\Livewire\Dashboard\IntegrationsPage;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function gscConfigure(): void
{
    config()->set('analytics.search_console.client_id', 'client-id-123');
    config()->set('analytics.search_console.client_secret', 'secret-456');
}

function gscConnection(array $attrs = []): SearchConsoleConnection
{
    return SearchConsoleConnection::query()->create(array_merge([
        'refresh_token' => 'refresh-token-plain',
        'access_token' => 'access-token-plain',
        'token_expires_at' => now()->addHour(),
        'status' => SearchConsoleConnection::STATUS_CONNECTED,
        'property' => 'sc-domain:example.com',
    ], $attrs));
}

beforeEach(function () {
    $this->admin = TestAdmin::create([]);
});

it('reports the feature unconfigured until both oauth credentials are set', function () {
    expect(app(SearchConsoleAuth::class)->configured())->toBeFalse();

    config()->set('analytics.search_console.client_id', 'client-id-123');
    expect(app(SearchConsoleAuth::class)->configured())->toBeFalse();

    config()->set('analytics.search_console.client_secret', 'secret-456');
    expect(app(SearchConsoleAuth::class)->configured())->toBeTrue();
});

it('redirects the connect route to google with the expected oauth parameters and a session state', function () {
    gscConfigure();
    $this->actingAs($this->admin, 'admin');

    $response = $this->get(route('analytics.integrations.search-console.connect'));

    $state = session('analytics.search_console.state');
    expect($state)->toBeString()->not->toBe('');

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($query['client_id'])->toBe('client-id-123')
        ->and($query['scope'])->toBe('https://www.googleapis.com/auth/webmasters.readonly')
        ->and($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('consent')
        ->and($query['state'])->toBe($state)
        ->and($query['redirect_uri'])->toBe(route('analytics.integrations.search-console.callback'));
});

it('sends the connect route back to the integrations page when unconfigured', function () {
    $this->actingAs($this->admin, 'admin');

    $this->get(route('analytics.integrations.search-console.connect'))
        ->assertRedirect(route('analytics.integrations'));
});

it('rejects a callback whose state does not match the session', function () {
    gscConfigure();
    $this->actingAs($this->admin, 'admin');

    $this->withSession(['analytics.search_console.state' => 'expected-state'])
        ->get(route('analytics.integrations.search-console.callback', ['state' => 'forged', 'code' => 'abc']))
        ->assertRedirect(route('analytics.integrations'));

    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

it('rejects a denied or codeless callback without storing anything', function () {
    gscConfigure();
    $this->actingAs($this->admin, 'admin');

    $this->withSession(['analytics.search_console.state' => 'state-1'])
        ->get(route('analytics.integrations.search-console.callback', ['state' => 'state-1', 'error' => 'access_denied']))
        ->assertRedirect(route('analytics.integrations'));

    expect(SearchConsoleConnection::query()->count())->toBe(0);
});

it('exchanges the code on a valid callback and stores an encrypted pending connection', function () {
    gscConfigure();
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
    expect($connection)->not->toBeNull()
        ->and($connection->status)->toBe(SearchConsoleConnection::STATUS_PENDING_PROPERTY)
        ->and($connection->property)->toBeNull()
        ->and($connection->refresh_token)->toBe('1//refresh');

    $raw = (string) DB::table('falcon_analytics_search_console')->value('refresh_token');
    expect($raw)->not->toContain('1//refresh');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code');
});

it('replaces a previous connection when a new callback succeeds', function () {
    gscConfigure();
    gscConnection();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r2', 'expires_in' => 3599]),
    ]);
    $this->actingAs($this->admin, 'admin');

    $this->withSession(['analytics.search_console.state' => 's'])
        ->get(route('analytics.integrations.search-console.callback', ['state' => 's', 'code' => 'c']));

    expect(SearchConsoleConnection::query()->count())->toBe(1)
        ->and(SearchConsoleConnection::current()->refresh_token)->toBe('r2');
});

it('reuses a fresh cached access token without calling google', function () {
    gscConfigure();
    Http::fake();
    $connection = gscConnection(['token_expires_at' => now()->addMinutes(30)]);

    expect(app(SearchConsoleAuth::class)->accessTokenFor($connection))->toBe('access-token-plain');
    Http::assertNothingSent();
});

it('refreshes an expired access token transparently and persists it', function () {
    gscConfigure();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
    ]);
    $connection = gscConnection(['token_expires_at' => now()->subMinute()]);

    expect(app(SearchConsoleAuth::class)->accessTokenFor($connection))->toBe('fresh-token')
        ->and($connection->refresh()->access_token)->toBe('fresh-token');

    Http::assertSent(fn ($request): bool => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'refresh-token-plain');
});

it('flags the connection when the refresh is rejected', function () {
    gscConfigure();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);
    $connection = gscConnection(['token_expires_at' => now()->subMinute()]);

    expect(fn () => app(SearchConsoleAuth::class)->accessTokenFor($connection))->toThrow(RuntimeException::class);

    expect($connection->refresh()->status)->toBe(SearchConsoleConnection::STATUS_ERROR)
        ->and($connection->last_error)->toContain('invalid_grant');
});

it('renders the unconfigured card with the env keys to provide', function () {
    $this->actingAs($this->admin, 'admin');

    $this->get(route('analytics.integrations'))
        ->assertSuccessful()
        ->assertSeeText(__('Intégrations'))
        ->assertSeeText('ANALYTICS_GSC_CLIENT_ID');
});

it('renders the connect button when configured and disconnected', function () {
    gscConfigure();
    $this->actingAs($this->admin, 'admin');

    $this->get(route('analytics.integrations'))
        ->assertSuccessful()
        ->assertSeeText(__('Connecter Google Search Console'));
});

it('lists the verified properties while the connection awaits its property', function () {
    gscConfigure();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
            ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
            ['siteUrl' => 'https://unverified.example/', 'permissionLevel' => 'siteUnverifiedUser'],
        ]]),
    ]);
    gscConnection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(IntegrationsPage::class)
        ->assertSeeText('sc-domain:example.com')
        ->assertDontSeeText('unverified.example')
        ->assertSeeText(__('Rattacher'));
});

it('attaches a listed property and marks the connection connected', function () {
    gscConfigure();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
            ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
        ]]),
    ]);
    gscConnection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(IntegrationsPage::class)
        ->call('selectProperty', 'sc-domain:example.com')
        ->assertDispatched('toast');

    $connection = SearchConsoleConnection::current();
    expect($connection->status)->toBe(SearchConsoleConnection::STATUS_CONNECTED)
        ->and($connection->property)->toBe('sc-domain:example.com');
});

it('refuses to attach a property google did not list', function () {
    gscConfigure();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []]),
    ]);
    gscConnection(['status' => SearchConsoleConnection::STATUS_PENDING_PROPERTY, 'property' => null]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(IntegrationsPage::class)->call('selectProperty', 'sc-domain:forged.com');

    expect(SearchConsoleConnection::current()->status)->toBe(SearchConsoleConnection::STATUS_PENDING_PROPERTY);
});

it('shows the connected state with the attached property', function () {
    gscConfigure();
    gscConnection();
    $this->actingAs($this->admin, 'admin');

    $this->get(route('analytics.integrations'))
        ->assertSuccessful()
        ->assertSeeText('sc-domain:example.com')
        ->assertSeeText(__('Connectée'))
        ->assertSeeText(__('Jamais'));
});

it('disconnects through the confirmation modal, revoking the token and deleting the row', function () {
    gscConfigure();
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);
    gscConnection();
    $this->actingAs($this->admin, 'admin');

    Livewire::test(IntegrationsPage::class)
        ->call('confirmDisconnect')
        ->assertSet('modal', 'disconnect')
        ->call('disconnectConfirmed')
        ->assertSet('modal', '')
        ->assertDispatched('toast');

    expect(SearchConsoleConnection::query()->count())->toBe(0);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://oauth2.googleapis.com/revoke'));
});

it('redirects a guest away from the integrations page and oauth routes', function () {
    gscConfigure();

    $this->get(route('analytics.integrations'))->assertRedirect(route('login'));
    $this->get(route('analytics.integrations.search-console.connect'))->assertRedirect(route('login'));
    $this->get(route('analytics.integrations.search-console.callback'))->assertRedirect(route('login'));
});
