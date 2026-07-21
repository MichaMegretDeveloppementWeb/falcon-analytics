<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Repositories\Dashboard\SearchQueryReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function gscWidgetConnection(array $attrs = []): SearchConsoleConnection
{
    return SearchConsoleConnection::query()->create(array_merge([
        'refresh_token' => 'refresh-token-plain',
        'status' => SearchConsoleConnection::STATUS_CONNECTED,
        'property' => 'sc-domain:example.com',
    ], $attrs));
}

function gscQueryRow(string $date, string $query, int $clicks, int $impressions, float $position): void
{
    SearchQuery::query()->create(compact('date', 'query', 'clicks', 'impressions', 'position'));
}

beforeEach(function () {
    $this->admin = TestAdmin::create([]);
    $this->travelTo(CarbonImmutable::parse('2026-07-20 12:00:00'));

    // Render the lazy widget's real content instead of its placeholder.
    Livewire::withoutLazyLoading();
});

it('aggregates the period queries by clicks with an impressions-weighted position and the previous-period clicks', function () {
    gscQueryRow('2026-07-10', 'louer une voiture', 10, 100, 3.0);
    gscQueryRow('2026-07-11', 'louer une voiture', 20, 300, 5.0);
    gscQueryRow('2026-07-12', 'suv geneve', 5, 50, 8.0);
    gscQueryRow('2026-06-01', 'louer une voiture', 12, 200, 6.0); // previous period
    gscQueryRow('2025-01-01', 'louer une voiture', 99, 999, 1.0); // outside both periods

    $rows = app(SearchQueryReadRepository::class)->topQueries(Period::ofDays(30), 10);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['query'])->toBe('louer une voiture')
        ->and($rows[0]['clicks'])->toBe(30)
        ->and($rows[0]['impressions'])->toBe(400)
        ->and($rows[0]['ctr'])->toBe(7.5)
        ->and($rows[0]['position'])->toBe(4.5)
        ->and($rows[0]['previous'])->toBe(12)
        ->and($rows[1]['query'])->toBe('suv geneve')
        ->and($rows[1]['previous'])->toBe(0);
});

it('totals the period clicks against the previous period', function () {
    gscQueryRow('2026-07-10', 'louer une voiture', 10, 100, 3.0);
    gscQueryRow('2026-07-12', 'suv geneve', 5, 50, 8.0);
    gscQueryRow('2026-06-01', 'louer une voiture', 12, 200, 6.0); // previous period

    expect(app(SearchQueryReadRepository::class)->clicksTotals(Period::ofDays(30)))
        ->toBe(['current' => 15, 'previous' => 12]);
});

it('reports the freshest cached day of the period', function () {
    gscQueryRow('2026-07-15', 'louer une voiture', 1, 10, 2.0);
    gscQueryRow('2026-07-17', 'suv geneve', 1, 10, 2.0);

    expect(app(SearchQueryReadRepository::class)->freshestDate(Period::ofDays(30))?->toDateString())->toBe('2026-07-17')
        ->and(app(SearchQueryReadRepository::class)->freshestDate(Period::ofDays(7)))->not->toBeNull();
});

it('renders the call to action when no connection is attached', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewSearchQueries::class)
        ->assertSeeText(__('Connectez Google Search Console'))
        ->assertSeeText(__('Connecter Search Console'));
});

it('offers a reconnect when the connection is in error', function () {
    gscWidgetConnection(['status' => SearchConsoleConnection::STATUS_ERROR]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewSearchQueries::class)
        ->assertSeeText(__('Reconnecter Search Console'));
});

it('renders the top queries with their freshness note when connected', function () {
    gscWidgetConnection();
    gscQueryRow('2026-07-17', 'louer une voiture', 30, 400, 4.5);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewSearchQueries::class)
        ->assertSeeText('louer une voiture')
        ->assertSeeText(__('Position moy.'))
        ->assertSeeText(__('clics sur la période'))
        ->assertSeeText(__('Dernières données Google : :date', ['date' => CarbonImmutable::parse('2026-07-17')->isoFormat('D MMM')]))
        ->assertDontSeeText(__('Connectez Google Search Console'));
});

it('renders the empty state when connected without cached data on the period', function () {
    gscWidgetConnection();
    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewSearchQueries::class)
        ->assertSeeText(__('Aucune donnée sur la période'));
});

it('mounts the section on the overview only when the oauth credentials are configured', function () {
    $this->actingAs($this->admin, 'admin');

    $this->get(route('analytics.overview'))
        ->assertSuccessful()
        ->assertDontSeeText(__('Clics par recherches Google'));

    config()->set('analytics.search_console.client_id', 'client-id-123');
    config()->set('analytics.search_console.client_secret', 'secret-456');

    $this->get(route('analytics.overview'))
        ->assertSuccessful()
        ->assertSeeHtml('analytics-overview-search-queries');
});
