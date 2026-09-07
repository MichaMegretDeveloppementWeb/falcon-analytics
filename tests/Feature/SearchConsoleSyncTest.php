<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

final class SearchConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_does_nothing_without_an_attached_connection(): void
    {
        Http::fake();

        $this->artisan('analytics:search-console:sync')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, SearchQuery::query()->count());
    }

    public function test_it_pulls_the_rows_and_upserts_the_local_cache_stamping_the_sync_time(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
                ['keys' => ['2026-07-15', 'location voiture geneve'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 3.417],
                ['keys' => ['2026-07-16', 'louer suv'], 'clicks' => 4, 'impressions' => 90, 'ctr' => 0.044, 'position' => 7.9],
            ]]),
        ]);

        $connection = $this->connection();

        $this->artisan('analytics:search-console:sync')->assertSuccessful();

        $this->assertSame(2, SearchQuery::query()->count());

        $row = SearchQuery::query()->where('query', 'location voiture geneve')->firstOrFail();

        $this->assertSame(12, $row->clicks);
        $this->assertSame(340, $row->impressions);
        $this->assertSame(3.42, $row->position);
        $this->assertSame('2026-07-15', $row->date->toDateString());
        $this->assertNotNull($connection->refresh()->last_synced_at);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), rawurlencode('sc-domain:example.com'))
            && $request['dimensions'] === ['date', 'query']
            && $request['type'] === 'web');
    }

    public function test_it_re_syncs_the_trailing_days_from_the_last_sync_and_updates_existing_rows(): void
    {
        $this->configureCredentials();

        SearchQuery::query()->create(['date' => '2026-07-18', 'query' => 'louer suv', 'clicks' => 1, 'impressions' => 10, 'position' => 9.0]);

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [
                ['keys' => ['2026-07-18', 'louer suv'], 'clicks' => 6, 'impressions' => 120, 'position' => 5.1],
            ]]),
        ]);

        $this->connection(['last_synced_at' => CarbonImmutable::parse('2026-07-19 05:00:00')]);

        $this->artisan('analytics:search-console:sync')->assertSuccessful();

        $this->assertSame(1, SearchQuery::query()->count());
        $this->assertSame(6, SearchQuery::query()->firstOrFail()->clicks);

        Http::assertSent(fn ($request): bool => $request['startDate'] === '2026-07-16');
    }

    public function test_it_follows_the_api_pagination_until_a_short_page(): void
    {
        $this->configureCredentials();

        $fullPage = array_map(
            fn (int $i): array => ['keys' => ['2026-07-15', "query {$i}"], 'clicks' => 1, 'impressions' => 2, 'position' => 1.0],
            range(1, 3),
        );

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
                ->push(['rows' => $fullPage])
                ->push(['rows' => [['keys' => ['2026-07-16', 'last one'], 'clicks' => 1, 'impressions' => 2, 'position' => 1.0]]]),
        ]);

        $this->connection();

        $this->app->bind(
            SearchConsoleClient::class,
            fn (): SearchConsoleClient => new SearchConsoleClient(app(SearchConsoleAuth::class), rowLimit: 3),
        );

        $this->artisan('analytics:search-console:sync')->assertSuccessful();

        $this->assertSame(4, SearchQuery::query()->count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['startRow'] === 0 || $request['startRow'] === 3);
    }

    public function test_it_flags_the_connection_and_fails_when_the_api_rejects_the_sync(): void
    {
        $this->configureCredentials();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => 'quota'], 429),
        ]);

        $connection = $this->connection();

        $this->artisan('analytics:search-console:sync')->assertFailed();

        $this->assertSame(SearchConsoleConnection::STATUS_ERROR, $connection->refresh()->status);
        $this->assertStringContainsString('HTTP 429', $connection->last_error);
    }
}
