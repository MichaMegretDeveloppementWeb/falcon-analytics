<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Repositories\Dashboard\SearchQueryReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

final class SearchQueriesWidgetTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = TestAdmin::create([]);
        $this->travelTo(CarbonImmutable::parse('2026-07-20 12:00:00'));

        // Rendre le contenu réel du widget différé plutôt que son gabarit d'attente.
        Livewire::withoutLazyLoading();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function connection(array $attributes = []): SearchConsoleConnection
    {
        return SearchConsoleConnection::query()->create(array_merge([
            'refresh_token' => 'refresh-token-plain',
            'status' => SearchConsoleConnection::STATUS_CONNECTED,
            'property' => 'sc-domain:example.com',
        ], $attributes));
    }

    private function queryRow(string $date, string $query, int $clicks, int $impressions, float $position): void
    {
        SearchQuery::query()->create(compact('date', 'query', 'clicks', 'impressions', 'position'));
    }

    public function test_it_aggregates_the_period_queries_by_clicks_with_a_weighted_position_and_the_previous_clicks(): void
    {
        $this->queryRow('2026-07-10', 'louer une voiture', 10, 100, 3.0);
        $this->queryRow('2026-07-11', 'louer une voiture', 20, 300, 5.0);
        $this->queryRow('2026-07-12', 'suv geneve', 5, 50, 8.0);
        $this->queryRow('2026-06-01', 'louer une voiture', 12, 200, 6.0); // période précédente
        $this->queryRow('2025-01-01', 'louer une voiture', 99, 999, 1.0); // hors des deux périodes

        $rows = app(SearchQueryReadRepository::class)->topQueries(Period::ofDays(30), 10);

        $this->assertCount(2, $rows);
        $this->assertSame('louer une voiture', $rows[0]['query']);
        $this->assertSame(30, $rows[0]['clicks']);
        $this->assertSame(400, $rows[0]['impressions']);
        $this->assertSame(7.5, $rows[0]['ctr']);
        $this->assertSame(4.5, $rows[0]['position']);
        $this->assertSame(12, $rows[0]['previous']);
        $this->assertSame('suv geneve', $rows[1]['query']);
        $this->assertSame(0, $rows[1]['previous']);
    }

    public function test_it_totals_the_period_clicks_against_the_previous_period(): void
    {
        $this->queryRow('2026-07-10', 'louer une voiture', 10, 100, 3.0);
        $this->queryRow('2026-07-12', 'suv geneve', 5, 50, 8.0);
        $this->queryRow('2026-06-01', 'louer une voiture', 12, 200, 6.0); // période précédente

        $this->assertSame(
            ['current' => 15, 'previous' => 12],
            app(SearchQueryReadRepository::class)->clicksTotals(Period::ofDays(30)),
        );
    }

    public function test_it_reports_the_freshest_cached_day_of_the_period(): void
    {
        $this->queryRow('2026-07-15', 'louer une voiture', 1, 10, 2.0);
        $this->queryRow('2026-07-17', 'suv geneve', 1, 10, 2.0);

        $this->assertSame(
            '2026-07-17',
            app(SearchQueryReadRepository::class)->freshestDate(Period::ofDays(30))?->toDateString(),
        );

        $this->assertNotNull(app(SearchQueryReadRepository::class)->freshestDate(Period::ofDays(7)));
    }

    public function test_it_renders_the_call_to_action_when_no_connection_is_attached(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewSearchQueries::class)
            ->assertSeeText(__('Connectez Google Search Console'))
            ->assertSeeText(__('Connecter Search Console'));
    }

    public function test_it_offers_a_reconnect_when_the_connection_is_in_error(): void
    {
        $this->connection(['status' => SearchConsoleConnection::STATUS_ERROR]);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewSearchQueries::class)
            ->assertSeeText(__('Reconnecter Search Console'));
    }

    public function test_it_renders_the_top_queries_with_their_freshness_note_when_connected(): void
    {
        $this->connection();
        $this->queryRow('2026-07-17', 'louer une voiture', 30, 400, 4.5);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewSearchQueries::class)
            ->assertSeeText('louer une voiture')
            ->assertSeeText(__('Position moy.'))
            ->assertSeeText(__('clics sur la période'))
            ->assertSeeText(__('Dernières données Google : :date', [
                'date' => CarbonImmutable::parse('2026-07-17')->isoFormat('D MMM'),
            ]))
            ->assertDontSeeText(__('Connectez Google Search Console'));
    }

    public function test_it_renders_the_empty_state_when_connected_without_cached_data_on_the_period(): void
    {
        $this->connection();
        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewSearchQueries::class)
            ->assertSeeText(__('Aucune donnée sur la période'));
    }

    public function test_it_mounts_the_section_on_the_overview_only_when_the_oauth_credentials_are_configured(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.overview'))
            ->assertSuccessful()
            ->assertDontSeeText(__('Clics par recherches Google'));

        config()->set('analytics.search_console.client_id', 'client-id-123');
        config()->set('analytics.search_console.client_secret', 'secret-456');

        $this->get(route('analytics.overview'))
            ->assertSuccessful()
            ->assertSeeHtml('analytics-overview-search-queries');
    }
}
