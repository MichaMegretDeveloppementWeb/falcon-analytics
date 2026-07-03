<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Dashboard\OverviewPage;
use Falcon\Analytics\Livewire\Dashboard\SessionsPage;
use Falcon\Analytics\Livewire\Dashboard\Widgets\TrendChart;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * @return array{count: int, duplicates: int}
 */
function analyticsQueryBudget(callable $render): array
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $render();

    $signatures = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_'))
        ->map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']));

    return ['count' => $signatures->count(), 'duplicates' => $signatures->count() - $signatures->unique()->count()];
}

uses(RefreshDatabase::class);

function seedSession(array $attrs = []): Session
{
    $visitor = Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    return Session::create(array_merge([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
    ], $attrs));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->admin = TestAdmin::create([]);
});

it('mounts the dashboard at the configured prefix and route names', function () {
    expect(route('analytics.overview', absolute: false))->toBe('/admin/analytics')
        ->and(route('analytics.visitors', absolute: false))->toBe('/admin/analytics/visitors')
        ->and(route('analytics.funnels', absolute: false))->toBe('/admin/analytics/funnels')
        ->and(route('analytics.sessions', absolute: false))->toBe('/admin/analytics/sessions')
        ->and(route('analytics.sessions.show', ['session' => 1], absolute: false))->toBe('/admin/analytics/sessions/1');
});

it('protects the dashboard from guests', function () {
    $session = seedSession();

    $this->get(route('analytics.overview'))->assertRedirect(route('login'));
    $this->get(route('analytics.visitors'))->assertRedirect(route('login'));
    $this->get(route('analytics.funnels'))->assertRedirect(route('login'));
    $this->get(route('analytics.sessions'))->assertRedirect(route('login'));
    $this->get(route('analytics.sessions.show', $session))->assertRedirect(route('login'));
});

it('renders the overview digest for an authenticated admin', function () {
    $session = seedSession(['source' => 'google']);
    Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Pageview, 'route' => 'accueil', 'url' => 'https://vantadrive.test/accueil']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.overview'))
        ->assertSuccessful()
        ->assertSeeText(__('Vue d\'ensemble'))
        ->assertSeeText(__('Visiteurs'))
        ->assertSeeText(__('Durée moy. session'))
        ->assertSeeText(__('Taux de rebond'))
        ->assertSeeText('Google')
        ->assertSeeText('/accueil');
});

it('renders a graceful error state instead of a 500 when a dashboard read fails', function () {
    $this->actingAs($this->admin, 'admin');

    // Force a real read failure: an aggregation query hits a missing table.
    Schema::drop('falcon_analytics_events');

    Livewire::test(OverviewPage::class)->assertSee(__('Données indisponibles'));
});

it('renders the sessions list for an authenticated admin', function () {
    seedSession(['city' => 'Genève', 'subject_type' => 'client', 'subject_id' => 1]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.sessions'))
        ->assertSuccessful()
        ->assertSeeText(__('Sessions'))
        ->assertSeeText('Genève')
        ->assertSeeText('Client #1');
});

it('renders the visitors list for an authenticated admin', function () {
    $visitor = Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'subject_type' => 'client',
        'subject_id' => 1,
    ]);
    Session::create([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
        'city' => 'Genève',
        'country' => 'CH',
        'source' => 'google',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.visitors'))
        ->assertSuccessful()
        ->assertSeeText(__('Visiteurs'))
        ->assertSeeText(__('Nouveaux'))
        ->assertSeeText(__('Sessions / visiteur'))
        ->assertSeeText('Client #1')
        ->assertSeeText('Genève');
});

it('renders a session detail with its information and event timeline', function () {
    $session = seedSession(['city' => 'Paris', 'subject_type' => 'client', 'subject_id' => 3, 'device_type' => 'desktop', 'browser' => 'Chrome']);
    Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinutes(2), 'type' => EventType::Pageview, 'route' => 'catalog']);
    Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Click, 'name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'catalog']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.sessions.show', $session))
        ->assertSuccessful()
        ->assertSeeText(__('Parcours'))
        ->assertSee('Client #3')
        ->assertSeeText('Paris')
        ->assertSeeText('Chrome')
        ->assertSeeText('Nous contacter')
        ->assertSeeText('/catalog');
});

it('renders the declared funnels for an authenticated admin', function () {
    config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);

    $visitor = Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
    $session = Session::create([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
    Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => now()->subMinutes(2), 'type' => EventType::Pageview, 'route' => 'home']);
    Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Custom, 'name' => 'sample.action']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.funnels'))
        ->assertSuccessful()
        ->assertSeeText(__('Entonnoirs'))
        ->assertSeeText('Sample funnel');
});

it('defers the trend chart behind a skeleton placeholder', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(TrendChart::class, ['period' => 30])
        ->assertSee('animate-pulse', escape: false);
});

it('renders the overview within its query budget with no duplicate query', function () {
    seedSession(['source' => 'google', 'country' => 'FR', 'city' => 'Paris']);

    $this->actingAs($this->admin, 'admin');

    $budget = analyticsQueryBudget(fn () => Livewire::test(OverviewPage::class));

    expect($budget['count'])->toBeLessThanOrEqual(18)
        ->and($budget['duplicates'])->toBe(0);
});

it('renders the sessions list within its query budget with no duplicate query', function () {
    seedSession(['city' => 'Genève']);

    $this->actingAs($this->admin, 'admin');

    $budget = analyticsQueryBudget(fn () => Livewire::test(SessionsPage::class));

    expect($budget['count'])->toBeLessThanOrEqual(12)
        ->and($budget['duplicates'])->toBe(0);
});

it('recomputes the overview metrics when the period changes', function () {
    seedSession();                                    // today, in every window
    seedSession(['started_at' => now()->subDays(60)]); // only inside the 90 day window

    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewPage::class)
        ->assertSet('period', 30)
        ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 1.0)
        ->set('period', 90)
        ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 2.0);
});

it('filters the overview metrics by subject type', function () {
    seedSession(['subject_type' => 'client', 'subject_id' => 1]);
    seedSession(['subject_type' => 'lessor', 'subject_id' => 2]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(OverviewPage::class)
        ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 2.0)
        ->set('subject', 'client')
        ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 1.0);
});

it('searches and resets pagination on the sessions page', function () {
    seedSession(['city' => 'Paris']);
    seedSession(['city' => 'Genève']);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(SessionsPage::class)
        ->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 2)
        ->set('search', 'Genève')
        ->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 1);
});
