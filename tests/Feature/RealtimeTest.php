<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Dashboard\RealtimePage;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Locale;

final class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private RealtimeReadRepository $repository;

    private CarbonImmutable $onlineSince;

    private CarbonImmutable $windowSince;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->repository = new RealtimeReadRepository;
        $this->onlineSince = CarbonImmutable::now()->subSeconds(60);
        $this->windowSince = CarbonImmutable::now()->subMinutes(30);
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function visitor(?array $subject = null): Visitor
    {
        return Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'session_count' => 0,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sessionRow(array $attributes = [], ?Visitor $visitor = null): Session
    {
        $visitor ??= $this->visitor();
        $visitor->increment('session_count');

        return Session::create(array_merge([
            'visitor_id' => $visitor->id,
            'browser_key' => $visitor->uuid,
            'started_at' => now()->subMinutes(2),
            'last_activity_at' => now(),
            'is_bot' => false,
            'pageview_count' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(Session $session, EventType $type, array $attributes = []): Event
    {
        return Event::create(array_merge([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'occurred_at' => now()->subMinute(),
            'type' => $type,
        ], $attributes));
    }

    // ── Le dépôt ─────────────────────────────────────────────────────────

    public function test_it_counts_distinct_online_visitors_ignoring_stale_and_bots(): void
    {
        $twoDevices = $this->visitor();
        $this->sessionRow([], $twoDevices);
        $this->sessionRow(['last_activity_at' => now()->subSeconds(30)], $twoDevices); // même visiteur : compte une fois
        $this->sessionRow(['last_activity_at' => now()->subSeconds(30)]);
        $this->sessionRow(['last_activity_at' => now()->subMinutes(5)]); // périmée
        $this->sessionRow(['is_bot' => true]);                           // robot

        $this->assertSame(2, $this->repository->onlineCount($this->onlineSince, null));
    }

    public function test_it_counts_the_window_sessions_visitors_and_pageviews_activity_based(): void
    {
        $visitor = $this->visitor();
        $active = $this->sessionRow(['started_at' => now()->subHours(2)], $visitor); // commencée avant, toujours active
        $this->sessionRow([], $visitor);
        $this->sessionRow(['last_activity_at' => now()->subHours(1)]);               // hors fenêtre

        $this->event($active, EventType::Pageview);
        $this->event($active, EventType::Pageview, ['occurred_at' => now()->subHours(1)]); // hors fenêtre
        $this->event($active, EventType::Click);

        $counts = $this->repository->windowCounts($this->windowSince, null);

        $this->assertSame(2, $counts['sessions']);
        $this->assertSame(1, $counts['visitors']);
        $this->assertSame(1, $counts['pageviews']);
    }

    public function test_it_counts_the_declared_conversions_fired_within_the_window(): void
    {
        $session = $this->sessionRow();
        $this->event($session, EventType::Custom, ['name' => 'Lead']);
        $this->event($session, EventType::Custom, ['name' => 'Lead', 'occurred_at' => now()->subHours(2)]);
        $this->event($session, EventType::Custom, ['name' => 'newsletter']);

        $this->assertSame(1, $this->repository->conversionsCount($this->windowSince, null, ['Lead']));
        $this->assertSame(0, $this->repository->conversionsCount($this->windowSince, null, []));
    }

    public function test_it_buckets_the_window_pageviews_per_minute(): void
    {
        $session = $this->sessionRow();
        $this->event($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:58:10')]);
        $this->event($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:58:50')]);
        $this->event($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:59:20')]);

        $this->assertSame([
            '2026-07-10 11:58' => 2,
            '2026-07-10 11:59' => 1,
        ], $this->repository->pageviewsPerMinute($this->windowSince, null));
    }

    public function test_it_bounds_and_orders_the_activity_feed_newest_first(): void
    {
        $session = $this->sessionRow();
        $this->event($session, EventType::Pageview, ['occurred_at' => now()->subMinutes(3), 'url' => 'https://x.test/old']);
        $this->event($session, EventType::Click, ['occurred_at' => now()->subMinutes(2), 'target_text' => 'Contact']);
        $this->event($session, EventType::Pageview, ['occurred_at' => now()->subMinute(), 'url' => 'https://x.test/new']);

        $feed = $this->repository->activityFeed($this->windowSince, null, 2);

        $this->assertCount(2, $feed);
        $this->assertSame('https://x.test/new', $feed->first()->url);
        $this->assertSame('Contact', $feed->last()->target_text);
        $this->assertTrue($feed->first()->relationLoaded('session'));
    }

    public function test_it_bounds_and_orders_the_recent_sessions_most_recently_active_first(): void
    {
        $this->sessionRow(['last_activity_at' => now()->subMinutes(3), 'city' => 'Old']);
        $this->sessionRow(['last_activity_at' => now()->subMinute(), 'city' => 'Mid']);
        $this->sessionRow(['last_activity_at' => now(), 'city' => 'Fresh']);
        $this->sessionRow(['last_activity_at' => now()->subHours(2), 'city' => 'Out']);

        $sessions = $this->repository->recentSessions($this->windowSince, null, 2);

        $this->assertCount(2, $sessions);
        $this->assertSame('Fresh', $sessions->first()->city);
        $this->assertSame('Mid', $sessions->last()->city);
        $this->assertTrue($sessions->first()->relationLoaded('visitor'));
    }

    public function test_it_ranks_the_window_top_pages_sources_and_devices_bounded(): void
    {
        $a = $this->sessionRow(['source' => 'organic', 'device_type' => 'desktop']);
        $this->sessionRow(['source' => 'organic', 'device_type' => 'mobile']);
        $this->sessionRow(['source' => 'direct', 'device_type' => 'desktop']);

        $this->event($a, EventType::Pageview, ['url' => 'https://x.test/a']);
        $this->event($a, EventType::Pageview, ['url' => 'https://x.test/a']);
        $this->event($a, EventType::Pageview, ['url' => 'https://x.test/b']);

        $this->assertSame(
            [['url' => 'https://x.test/a', 'total' => 2]],
            $this->repository->topPages($this->windowSince, null, 1),
        );

        $this->assertSame([
            ['label' => 'organic', 'total' => 2],
            ['label' => 'direct', 'total' => 1],
        ], $this->repository->topSources($this->windowSince, null));

        $this->assertSame(
            ['label' => 'desktop', 'total' => 2],
            $this->repository->topDevices($this->windowSince, null)[0],
        );
    }

    public function test_it_narrows_the_realtime_reads_to_a_subject_type(): void
    {
        $client = $this->visitor(['type' => 'client', 'id' => 3]);
        $clientSession = $this->sessionRow(['subject_type' => 'client', 'subject_id' => 3], $client);
        $this->event($clientSession, EventType::Pageview);

        $anonymous = $this->sessionRow();
        $this->event($anonymous, EventType::Pageview);

        $this->assertSame(1, $this->repository->onlineCount($this->onlineSince, 'client'));
        $this->assertSame(1, $this->repository->windowCounts($this->windowSince, 'client')['pageviews']);
        $this->assertSame(1, $this->repository->topSources($this->windowSince, 'client')[0]['total']);
    }

    public function test_it_aggregates_the_map_points_by_locality_with_online_counts_bounded(): void
    {
        $geneva = ['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432];

        $this->sessionRow($geneva);
        $this->sessionRow(array_merge($geneva, ['last_activity_at' => now()->subMinutes(10)])); // récente, pas en ligne
        $this->sessionRow(['city' => 'Paris', 'country' => 'FR', 'latitude' => 48.8566, 'longitude' => 2.3522]);
        $this->sessionRow(array_merge($geneva, ['is_bot' => true]));                            // robot : écarté
        $this->sessionRow(array_merge($geneva, ['last_activity_at' => now()->subHours(2)]));    // hors fenêtre

        $points = $this->repository->mapPoints($this->windowSince, $this->onlineSince);

        $this->assertCount(2, $points);
        $this->assertSame('Geneva', $points[0]['city']);
        $this->assertSame(2, $points[0]['total']);
        $this->assertSame(1, $points[0]['online']);
        $this->assertSame('Paris', $points[1]['city']);
        $this->assertSame(1, $points[1]['online']);
        $this->assertCount(1, $this->repository->mapPoints($this->windowSince, $this->onlineSince, 1));
    }

    public function test_it_counts_the_unlocated_window_sessions(): void
    {
        $this->sessionRow();
        $this->sessionRow(['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432]);

        $this->assertSame(1, $this->repository->unlocatedCount($this->windowSince));
    }

    // ── L'écran ──────────────────────────────────────────────────────────

    public function test_it_renders_the_realtime_page_for_an_admin_with_the_configured_poll(): void
    {
        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $visitor = $this->visitor(['type' => 'client', 'id' => $marie->id]);
        $session = $this->sessionRow([], $visitor);
        $this->event($session, EventType::Pageview, ['url' => 'https://x.test/catalogue']);

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.realtime'))
            ->assertSuccessful()
            ->assertSeeText(__('Temps réel'))
            ->assertSeeText(__('Visiteurs en ligne'))
            ->assertSeeText(__('Visiteurs récents'))
            ->assertSeeText('Marie Dupont')
            ->assertSee('wire:poll.10s.visible', false);
    }

    public function test_it_honours_an_overridden_realtime_configuration(): void
    {
        config()->set('analytics.realtime.poll_seconds', 5);

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.realtime'))
            ->assertSuccessful()
            ->assertSee('wire:poll.5s.visible', false);
    }

    public function test_it_redirects_a_guest_to_the_login_page(): void
    {
        $this->get(route('analytics.realtime'))->assertRedirect();
    }

    public function test_it_dispatches_the_fresh_series_for_the_live_charts_on_every_tick(): void
    {
        $session = $this->sessionRow([
            'device_type' => 'desktop',
            'source' => 'organic',
            'city' => 'Geneva',
            'country' => 'CH',
            'latitude' => 46.2044,
            'longitude' => 6.1432,
        ]);

        $this->event($session, EventType::Pageview, ['url' => 'https://x.test/a']);

        $this->actingAs(TestAdmin::create([]), 'admin');

        Livewire::test(RealtimePage::class)
            ->assertDispatched(
                'analytics-realtime-tick',
                fn (string $name, array $params): bool => isset($params['pulse'], $params['devices'], $params['sources'], $params['map'])
                    && $params['map']['points'][0]['city'] === 'Geneva'
                    && $params['map']['points'][0]['online'] === 1,
            );
    }

    public function test_it_renders_the_map_the_country_list_and_the_unlocated_note(): void
    {
        $this->sessionRow(['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432]);
        $this->sessionRow();

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.realtime'))
            ->assertSuccessful()
            ->assertSeeText(__('Pays'))
            ->assertSeeText(Locale::getDisplayRegion('-CH', app()->getLocale()))
            ->assertSeeText(__('dont 1 session non localisée'));
    }

    // ── Le budget ────────────────────────────────────────────────────────

    public function test_it_renders_the_realtime_tick_within_its_query_budget(): void
    {
        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $visitor = $this->visitor(['type' => 'client', 'id' => $marie->id]);
        $session = $this->sessionRow([], $visitor);
        $this->event($session, EventType::Pageview, ['url' => 'https://x.test/a']);
        $this->event($session, EventType::Click, ['target_text' => 'Contact']);
        $this->sessionRow();

        $this->actingAs(TestAdmin::create([]), 'admin');

        DB::enableQueryLog();
        DB::flushQueryLog();

        Livewire::test(RealtimePage::class);

        $signatures = Collection::make(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_'))
            ->map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']));

        // Plan figé : en ligne + fenêtre (sessions, pages vues) + seaux par
        // minute + sessions récentes (+ visiteurs) + flux (événements +
        // sessions + visiteurs) + levée d'ambiguïté de l'attributeur + top
        // pages/sources/appareils + points de carte + non localisées = 16.
        // Aucune lecture en double dans un tic.
        $this->assertSame(0, $signatures->count() - $signatures->unique()->count());
        $this->assertLessThanOrEqual(16, $signatures->count());
    }
}
