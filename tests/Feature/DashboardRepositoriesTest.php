<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\SessionListReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorProfileReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DashboardRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private EngagementReadRepository $engagement;

    private OverviewReadRepository $overview;

    private SessionListReadRepository $sessions;

    private VisitorListReadRepository $visitors;

    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->engagement = new EngagementReadRepository;
        $this->overview = new OverviewReadRepository(new MarketingReportBuilder);
        $this->sessions = new SessionListReadRepository;
        $this->visitors = new VisitorListReadRepository;
        $this->period = Period::ofDays(30);
    }

    private function makeVisitor(): Visitor
    {
        return Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSession(array $attributes = [], ?Visitor $visitor = null): Session
    {
        $visitor ??= $this->makeVisitor();
        $visitor->increment('session_count');

        return Session::create(array_merge([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
            'pageview_count' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEvent(Session $session, EventType $type, array $attributes = []): Event
    {
        return Event::create(array_merge([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'occurred_at' => now()->subMinute(),
            'type' => $type,
        ], $attributes));
    }

    public function test_it_fetches_raw_headline_counts_for_the_period_excluding_bots(): void
    {
        $this->makeSession(['pageview_count' => 2]);
        $this->makeSession(['pageview_count' => 2]);
        $this->makeSession(['pageview_count' => 2]);
        $this->makeSession(['pageview_count' => 1, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(4)]);
        $this->makeSession(['pageview_count' => 9, 'is_bot' => true]);

        $counts = $this->engagement->headlineCounts($this->period, null);

        $this->assertSame(4, $counts['sessions']);
        $this->assertSame(4, $counts['visitors']);
        $this->assertSame(7, $counts['pageviews']);
        $this->assertSame(1, $counts['bounces']);
        $this->assertSame(15, (int) round($counts['avgSeconds']), '(0 + 0 + 0 + 60) / 4');
    }

    public function test_it_narrows_the_raw_headline_counts_to_a_subject_type(): void
    {
        $client = $this->makeVisitor();
        $this->makeSession(['subject_type' => 'client', 'subject_id' => 1], $client);
        $this->makeSession(['subject_type' => 'client', 'subject_id' => 1], $client);
        $this->makeSession(['subject_type' => 'lessor', 'subject_id' => 7]);

        $counts = $this->engagement->headlineCounts($this->period, 'client');

        $this->assertSame(2, $counts['sessions']);
        $this->assertSame(1, $counts['visitors']);
    }

    public function test_it_fetches_raw_today_and_yesterday_counts_for_the_spotlight(): void
    {
        $this->makeSession();
        $this->makeSession();
        $this->makeSession(['started_at' => now()->subDay(), 'last_activity_at' => now()->subDay()]);

        $spotlight = $this->engagement->spotlightCounts(null);

        $this->assertSame(2, $spotlight['today']['sessions']);
        $this->assertSame(2, $spotlight['today']['visitors']);
        $this->assertSame(1, $spotlight['yesterday']['sessions']);
    }

    public function test_it_fetches_raw_daily_trend_rows_keyed_by_day_without_zero_fill(): void
    {
        $this->makeSession(['pageview_count' => 1]);
        $this->makeSession(['pageview_count' => 1]);
        $this->makeSession(['pageview_count' => 4, 'started_at' => now()->subDays(2)]);

        $rows = $this->overview->trendRows(Period::ofDays(7), null);

        $this->assertSame(['sessions' => 2, 'pageviews' => 2], $rows[now()->format('Y-m-d')]);
        $this->assertSame(4, $rows[now()->subDays(2)->format('Y-m-d')]['pageviews']);
        $this->assertArrayNotHasKey(now()->subDay()->format('Y-m-d'), $rows);
    }

    public function test_it_fetches_raw_daily_sparkline_rows_keyed_by_day(): void
    {
        $this->makeSession(['pageview_count' => 2]);
        $this->makeSession(['pageview_count' => 2]);
        $this->makeSession(['pageview_count' => 1, 'started_at' => now()->subDays(2), 'last_activity_at' => now()->subDays(2)]);

        $rows = $this->engagement->sparklineRows(Period::ofDays(7), null);
        $today = $rows[now()->format('Y-m-d')];

        $this->assertSame(2, $today['sessions']);
        $this->assertSame(2, $today['visitors']);
        $this->assertSame(4, $today['pageviews']);
        $this->assertSame(0, $today['bounces']);
        $this->assertArrayHasKey(now()->subDays(2)->format('Y-m-d'), $rows);
    }

    public function test_it_ranks_the_top_sources_with_previous_period_counts(): void
    {
        $this->makeSession(['source' => 'google']);
        $this->makeSession(['source' => 'google']);
        $this->makeSession(['source' => 'facebook']);
        $this->makeSession(['source' => 'google', 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);

        $this->assertSame([
            ['label' => 'google', 'total' => 2, 'previous' => 1],
            ['label' => 'facebook', 'total' => 1, 'previous' => 0],
        ], $this->overview->topSources($this->period, null));
    }

    public function test_it_reclassifies_campaign_matched_sessions_as_paid_in_the_channel_breakdown(): void
    {
        Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);

        // Deux sessions classées organiques qui correspondent en fait à la
        // campagne, plus une qui n'y correspond pas.
        $this->makeSession(['source' => 'organic', 'mkt_params' => ['src' => 'meta_ete']]);
        $this->makeSession(['source' => 'organic', 'mkt_params' => ['src' => 'meta_ete']]);
        $this->makeSession(['source' => 'organic']);

        $sources = Collection::make($this->overview->topSources($this->period, null))->pluck('total', 'label');

        $this->assertSame(2, $sources['paid'] ?? 0);
        $this->assertSame(1, $sources['organic'] ?? 0);
    }

    public function test_it_breaks_down_events_by_name_and_flags_declared_conversions(): void
    {
        $session = $this->makeSession();
        $this->makeEvent($session, EventType::Click, ['name' => 'Lead']);
        $this->makeEvent($session, EventType::Click, ['name' => 'Lead']);
        $this->makeEvent($session, EventType::Click, ['name' => 'cta.contact']);

        $registry = new EventRegistry;
        $registry->register(new TrackedEvent('Lead', 'Demande de code', 3.0));

        $breakdown = (new EventReadRepository)->eventBreakdown($this->period, null, $registry);

        $this->assertCount(2, $breakdown);
        $this->assertSame('Lead', $breakdown[0]['name']);
        $this->assertSame('Demande de code', $breakdown[0]['label']);
        $this->assertSame(2, $breakdown[0]['count']);
        $this->assertTrue($breakdown[0]['isConversion']);
        $this->assertSame(6.0, $breakdown[0]['valueTotal']);
        $this->assertSame('cta.contact', $breakdown[1]['name']);
        $this->assertSame(1, $breakdown[1]['count']);
        $this->assertFalse($breakdown[1]['isConversion']);
    }

    public function test_it_counts_named_events_and_conversions_per_session_in_the_list(): void
    {
        $session = $this->makeSession();
        $this->makeEvent($session, EventType::Click, ['name' => 'cta.contact']);
        $this->makeEvent($session, EventType::Click, ['name' => 'Lead']);
        $this->makeEvent($session, EventType::Pageview); // sans nom, donc écarté

        $result = (new SessionListReadRepository)->paginateSessions(
            $this->period, null, null, null, null, app(SubjectResolver::class), conversionNames: ['Lead'],
        );

        $row = $result->first();

        $this->assertSame(2, (int) $row->events_count);
        $this->assertSame(1, (int) $row->conversions_count);
    }

    public function test_it_sorts_the_session_list_by_the_event_count(): void
    {
        $quiet = $this->makeSession();
        $this->makeEvent($quiet, EventType::Click, ['name' => 'cta.contact']);

        $busy = $this->makeSession();
        $this->makeEvent($busy, EventType::Click, ['name' => 'cta.contact']);
        $this->makeEvent($busy, EventType::Click, ['name' => 'Lead']);

        $result = (new SessionListReadRepository)->paginateSessions(
            $this->period, null, null, null, null, app(SubjectResolver::class), 'events_count', 'desc', 20, ['Lead'],
        );

        $this->assertSame($busy->id, $result->first()->id);
    }

    public function test_it_ranks_the_top_localities(): void
    {
        $this->makeSession(['country' => 'FR', 'city' => 'Paris']);
        $this->makeSession(['country' => 'FR', 'city' => 'Paris']);
        $this->makeSession(['country' => 'FR', 'city' => 'Lyon']);
        $this->makeSession(['country' => 'CH', 'city' => 'Genève']);

        $localities = $this->overview->topLocalities($this->period, null);

        $this->assertCount(3, $localities);
        $this->assertSame(['country' => 'FR', 'city' => 'Paris', 'total' => 2, 'previous' => 0], $localities[0]);
    }

    public function test_it_ranks_the_most_viewed_pages_by_their_real_url_excluding_bot_sessions(): void
    {
        $session = $this->makeSession();
        $this->makeEvent($session, EventType::Pageview, ['route' => 'listing.detail', 'url' => 'https://x.test/listings/25']);
        $this->makeEvent($session, EventType::Pageview, ['route' => 'listing.detail', 'url' => 'https://x.test/listings/25']);
        $this->makeEvent($session, EventType::Pageview, ['route' => 'catalog', 'url' => 'https://x.test/catalog']);

        $bot = $this->makeSession(['is_bot' => true]);
        $this->makeEvent($bot, EventType::Pageview, ['route' => 'home', 'url' => 'https://x.test/']);

        $this->assertSame([
            // La même adresse s'agrège.
            ['label' => 'https://x.test/listings/25', 'total' => 2, 'previous' => 0],
            ['label' => 'https://x.test/catalog', 'total' => 1, 'previous' => 0],
        ], $this->overview->topPages($this->period, null));
    }

    public function test_it_ranks_the_top_clicks_preferring_the_visible_text_over_the_technical_name(): void
    {
        $session = $this->makeSession();
        $this->makeEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
        $this->makeEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
        $this->makeEvent($session, EventType::Click, ['name' => 'auth.login', 'route' => 'client.login']);

        $this->assertSame([
            ['label' => 'Nous contacter', 'route' => 'home', 'total' => 2],
            ['label' => 'auth.login', 'route' => 'client.login', 'total' => 1],
        ], $this->overview->topClicks($this->period, null));
    }

    public function test_it_splits_new_and_returning_visitor_counts(): void
    {
        $this->makeSession();

        $returning = $this->makeVisitor();
        $returning->update(['first_seen_at' => now()->subMonths(3)]);
        $this->makeSession([], $returning);

        $this->assertSame(['new' => 1, 'returning' => 1], $this->overview->newVsReturning($this->period, null));
    }

    public function test_it_counts_sessions_by_device_type_busiest_first(): void
    {
        $this->makeSession(['device_type' => 'desktop']);
        $this->makeSession(['device_type' => 'desktop']);
        $this->makeSession(['device_type' => 'mobile']);
        $this->makeSession(['device_type' => null]);

        $this->assertSame(['desktop' => 2, 'mobile' => 1], $this->overview->sessionsByDevice($this->period, null));
    }

    public function test_it_paginates_sessions_newest_first_excluding_bots_with_filters(): void
    {
        foreach (range(1, 22) as $i) {
            $this->makeSession(['city' => 'Paris', 'device_type' => 'desktop', 'started_at' => now()->subMinutes($i)]);
        }

        $this->makeSession(['city' => 'Geneva', 'device_type' => 'mobile']);
        $this->makeSession(['city' => 'Paris', 'is_bot' => true]);

        $subjects = new SubjectResolver;
        $all = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects);
        $byCity = $this->sessions->paginateSessions($this->period, null, 'Geneva', null, null, $subjects);
        $byDevice = $this->sessions->paginateSessions($this->period, null, null, 'mobile', null, $subjects);

        $this->assertSame(23, $all->total());
        $this->assertSame(20, $all->count());
        $this->assertSame(1, $byCity->total());
        $this->assertSame(1, $byDevice->total());
        $this->assertSame('Geneva', $byDevice->first()->city);
    }

    public function test_it_sorts_sessions_by_a_whitelisted_column_and_direction(): void
    {
        $this->makeSession(['pageview_count' => 3]);
        $this->makeSession(['pageview_count' => 9]);
        $this->makeSession(['pageview_count' => 1]);

        $subjects = new SubjectResolver;
        $asc = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects, 'pageview_count', 'asc');
        $desc = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects, 'pageview_count', 'desc');

        $this->assertSame(1, $asc->first()->pageview_count);
        $this->assertSame(9, $desc->first()->pageview_count);
    }

    public function test_it_searches_sessions_by_visitor_name_resolved_from_the_guard_model(): void
    {
        config()->set('analytics.identity.subjects.client', ['label' => 'Client', 'name' => ['first_name', 'last_name']]);

        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $this->makeSession(['subject_type' => 'client', 'subject_id' => $marie->id]);
        $this->makeSession(['subject_type' => 'client', 'subject_id' => 999]);

        $found = $this->sessions->paginateSessions($this->period, null, 'Marie', null, null, new SubjectResolver);

        $this->assertSame(1, $found->total());
        $this->assertSame($marie->id, $found->first()->subject_id);
    }

    public function test_it_searches_anonymous_sessions_by_the_subject_stitched_on_their_visitor(): void
    {
        config()->set('analytics.identity.subjects.client', ['label' => 'Client', 'name' => ['first_name', 'last_name']]);

        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $visitor = $this->makeVisitor();
        $visitor->update(['subject_type' => 'client', 'subject_id' => $marie->id]);
        $anonymous = $this->makeSession([], $visitor);
        $this->makeSession();

        $found = $this->sessions->paginateSessions($this->period, null, 'Marie', null, null, new SubjectResolver);

        $this->assertSame(1, $found->total());
        $this->assertSame($anonymous->id, $found->first()->id);
    }

    public function test_it_searches_sessions_by_the_visitor_uuid_shown_as_the_id(): void
    {
        $visitor = Visitor::create(['uuid' => 'vd-known-42', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $this->makeSession([], $visitor);
        $this->makeSession();

        $found = $this->sessions->paginateSessions($this->period, null, 'vd-known', null, null, new SubjectResolver);

        $this->assertSame(1, $found->total());
        $this->assertSame($visitor->id, $found->first()->visitor_id);
    }

    public function test_it_searches_sessions_by_country_name_resolving_the_stored_iso_code(): void
    {
        $this->makeSession(['country' => 'FR']);
        $this->makeSession(['country' => 'CH']);

        $found = $this->sessions->paginateSessions($this->period, null, 'France', null, null, new SubjectResolver);

        $this->assertSame(1, $found->total());
        $this->assertSame('FR', $found->first()->country);
    }

    public function test_it_paginates_the_all_time_visitor_directory_with_its_derived_columns(): void
    {
        $visitor = $this->makeVisitor();
        $this->makeSession(['source' => 'organic', 'started_at' => now()->subDays(60)], $visitor); // la toute première : acquisition
        $this->makeSession(['city' => 'Lyon', 'source' => 'referral', 'started_at' => now()->subDays(3)], $visitor);
        $this->makeSession(['city' => 'Paris', 'source' => 'paid', 'started_at' => now()->subDay()], $visitor); // la dernière : localité

        $this->makeSession(['is_bot' => true], $this->makeVisitor()); // visiteur robot seulement : écarté

        $alias = $this->makeVisitor(); // alias fusionné : écarté
        $alias->update(['merged_into_id' => $visitor->id]);

        $result = $this->visitors->paginateVisitors(null, null, new SubjectResolver);

        $this->assertSame(1, $result->total());

        $row = $result->first();

        $this->assertSame($visitor->id, (int) $row->id);
        $this->assertSame(3, (int) $row->session_count, 'de tout temps, celle de soixante jours comprise');
        $this->assertSame('Paris', $row->last_city, 'la session la plus récente');
        $this->assertSame('organic', $row->acquisition_source, 'la toute première session');
    }

    public function test_it_sorts_visitors_by_their_all_time_session_count(): void
    {
        $busy = $this->makeVisitor();
        $this->makeSession([], $busy);
        $this->makeSession([], $busy);
        $this->makeSession([], $this->makeVisitor());

        $desc = $this->visitors->paginateVisitors(null, null, new SubjectResolver, 'session_count', 'desc');

        $this->assertSame(2, (int) $desc->first()->session_count);
        $this->assertSame($busy->id, (int) $desc->first()->id);
    }

    public function test_it_counts_period_visitors_new_visitors_and_sessions_excluding_bots(): void
    {
        $returning = $this->makeVisitor();
        $returning->update(['first_seen_at' => now()->subDays(60)]); // vu avant la période
        $this->makeSession([], $returning);
        $this->makeSession([], $returning);

        $new = $this->makeVisitor();
        $new->update(['first_seen_at' => now()->subDay()]); // vu pour la première fois dans la période
        $this->makeSession([], $new);

        $this->makeSession(['is_bot' => true], $this->makeVisitor()); // robot : écarté

        $counts = $this->visitors->visitorCounts($this->period, null);

        $this->assertSame(2, $counts['visitors']);
        $this->assertSame(1, $counts['new']);
        $this->assertSame(3, $counts['sessions']);
    }

    public function test_it_returns_raw_daily_rows_keyed_by_day_with_new_visitors_bot_excluded(): void
    {
        $visitor = $this->makeVisitor();
        $visitor->update(['first_seen_at' => CarbonImmutable::parse('2026-06-10 09:00')]);
        $this->makeSession(['started_at' => CarbonImmutable::parse('2026-06-10 09:00')], $visitor);

        $botVisitor = $this->makeVisitor();
        $botVisitor->update(['first_seen_at' => CarbonImmutable::parse('2026-06-10 10:00')]);
        $this->makeSession(['is_bot' => true, 'started_at' => CarbonImmutable::parse('2026-06-10 10:00')], $botVisitor);

        $rows = $this->visitors->visitorDailyRows($this->period, null);

        $this->assertSame(['sessions' => 1, 'visitors' => 1], $rows['active']['2026-06-10']);
        $this->assertSame(1, $rows['new']['2026-06-10'], 'le visiteur robot est écarté des nouveaux');
    }

    public function test_it_fetches_the_visitors_screen_data_within_its_query_budget(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeSession(['city' => 'Paris']);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->visitors->paginateVisitors(null, null, new SubjectResolver);
        $this->visitors->visitorCounts($this->period, null);
        $this->visitors->visitorCounts($this->period->previous(), null);
        $this->visitors->visitorDailyRows($this->period, null);

        $queries = DB::getQueryLog();
        $signatures = array_map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']), $queries);

        // Plan figé : pagination (compte + sélection) + compteurs deux fois
        // (totaux + nouveaux) + quotidien (actifs + nouveaux) = 8.
        $this->assertCount(8, $queries);
        $this->assertSame(array_values(array_unique($signatures)), $signatures, 'rien n’est lu deux fois');
    }

    public function test_it_aggregates_a_visitor_engagement_over_all_their_sessions_and_scopes_to_them(): void
    {
        $visitor = $this->makeVisitor();
        $this->makeSession(['device_type' => 'mobile', 'source' => 'google', 'pageview_count' => 3], $visitor);
        $this->makeSession(['device_type' => 'mobile', 'source' => 'google', 'pageview_count' => 2], $visitor);
        $this->makeSession(['device_type' => 'desktop', 'source' => null, 'pageview_count' => 1], $visitor);

        // La session d'un autre visiteur ne doit jamais fuir dans l'agrégat.
        $this->makeSession(['device_type' => 'tablet', 'source' => 'social', 'pageview_count' => 9]);

        $engagement = app(VisitorProfileReadRepository::class)->engagement($visitor->id);

        $this->assertSame(3, $engagement['sessions']);
        $this->assertSame(6, $engagement['pageviews']);
        $this->assertSame(['mobile' => 2, 'desktop' => 1], $engagement['devices']);
        $this->assertSame(['google' => 2, 'direct' => 1], $engagement['sources']);
    }

    public function test_it_paginates_a_visitor_sessions_list(): void
    {
        $visitor = $this->makeVisitor();

        foreach (range(1, 25) as $i) {
            $this->makeSession(['started_at' => now()->subMinutes($i)], $visitor);
        }

        $page = app(VisitorProfileReadRepository::class)->paginateSessions($visitor->id, 20);

        $this->assertSame(25, $page->total());
        $this->assertSame(20, $page->perPage());
        $this->assertSame(20, $page->count());
    }
}
