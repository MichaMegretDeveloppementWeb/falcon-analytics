<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function gscSyncConfigure(): void
{
    config()->set('analytics.search_console.client_id', 'client-id-123');
    config()->set('analytics.search_console.client_secret', 'secret-456');
}

function gscSyncConnection(array $attrs = []): SearchConsoleConnection
{
    return SearchConsoleConnection::query()->create(array_merge([
        'refresh_token' => 'refresh-token-plain',
        'access_token' => 'access-token-plain',
        'token_expires_at' => now()->addHour(),
        'status' => SearchConsoleConnection::STATUS_CONNECTED,
        'property' => 'sc-domain:example.com',
    ], $attrs));
}

it('does nothing without an attached connection', function () {
    Http::fake();

    $this->artisan('analytics:search-console:sync')->assertSuccessful();

    Http::assertNothingSent();
    expect(SearchQuery::query()->count())->toBe(0);
});

it('pulls the rows and upserts the local cache, stamping the sync time', function () {
    gscSyncConfigure();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
            ['keys' => ['2026-07-15', 'location voiture geneve'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 3.417],
            ['keys' => ['2026-07-16', 'louer suv'], 'clicks' => 4, 'impressions' => 90, 'ctr' => 0.044, 'position' => 7.9],
        ]]),
    ]);
    $connection = gscSyncConnection();

    $this->artisan('analytics:search-console:sync')->assertSuccessful();

    expect(SearchQuery::query()->count())->toBe(2);

    $row = SearchQuery::query()->where('query', 'location voiture geneve')->firstOrFail();
    expect($row->clicks)->toBe(12)
        ->and($row->impressions)->toBe(340)
        ->and($row->position)->toBe(3.42)
        ->and($row->date->toDateString())->toBe('2026-07-15');

    expect($connection->refresh()->last_synced_at)->not->toBeNull();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), rawurlencode('sc-domain:example.com'))
            && $request['dimensions'] === ['date', 'query']
            && $request['type'] === 'web';
    });
});

it('re-syncs the trailing days from the last sync and updates existing rows', function () {
    gscSyncConfigure();
    SearchQuery::query()->create(['date' => '2026-07-18', 'query' => 'louer suv', 'clicks' => 1, 'impressions' => 10, 'position' => 9.0]);
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
            ['keys' => ['2026-07-18', 'louer suv'], 'clicks' => 6, 'impressions' => 120, 'position' => 5.1],
        ]]),
    ]);
    gscSyncConnection(['last_synced_at' => CarbonImmutable::parse('2026-07-19 05:00:00')]);

    $this->artisan('analytics:search-console:sync')->assertSuccessful();

    expect(SearchQuery::query()->count())->toBe(1)
        ->and(SearchQuery::query()->firstOrFail()->clicks)->toBe(6);

    Http::assertSent(fn ($request): bool => $request['startDate'] === '2026-07-16');
});

it('follows the api pagination until a short page', function () {
    gscSyncConfigure();
    $fullPage = array_map(
        fn (int $i): array => ['keys' => ['2026-07-15', "query {$i}"], 'clicks' => 1, 'impressions' => 2, 'position' => 1.0],
        range(1, 3),
    );
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
            ->push(['rows' => $fullPage])
            ->push(['rows' => [['keys' => ['2026-07-16', 'last one'], 'clicks' => 1, 'impressions' => 2, 'position' => 1.0]]]),
    ]);
    gscSyncConnection();

    $this->app->bind(
        SearchConsoleClient::class,
        fn (): SearchConsoleClient => new SearchConsoleClient(
            app(SearchConsoleAuth::class),
            rowLimit: 3,
        ),
    );

    $this->artisan('analytics:search-console:sync')->assertSuccessful();

    expect(SearchQuery::query()->count())->toBe(4);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request['startRow'] === 0 || $request['startRow'] === 3);
});

it('flags the connection and fails when the api rejects the sync', function () {
    gscSyncConfigure();
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => 'quota'], 429),
    ]);
    $connection = gscSyncConnection();

    $this->artisan('analytics:search-console:sync')->assertFailed();

    expect($connection->refresh()->status)->toBe(SearchConsoleConnection::STATUS_ERROR)
        ->and($connection->last_error)->toContain('HTTP 429');
});
