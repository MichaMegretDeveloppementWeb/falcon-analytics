<?php

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
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\SessionListReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorProfileReadRepository;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeDashboardVisitor(): Visitor
{
    return Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
}

function makeDashboardSession(array $attrs = [], ?Visitor $visitor = null): Session
{
    $visitor ??= makeDashboardVisitor();

    return Session::create(array_merge([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
    ], $attrs));
}

function makeDashboardEvent(Session $session, EventType $type, array $attrs = []): Event
{
    return Event::create(array_merge([
        'session_id' => $session->id,
        'visitor_id' => $session->visitor_id,
        'occurred_at' => now()->subMinute(),
        'type' => $type,
    ], $attrs));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->engagement = new EngagementReadRepository;
    $this->overview = new OverviewReadRepository(new MarketingReadRepository);
    $this->sessions = new SessionListReadRepository;
    $this->visitors = new VisitorListReadRepository;
    $this->period = Period::ofDays(30);
});

it('fetches raw headline counts for the period, excluding bots', function () {
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(4)]);
    makeDashboardSession(['pageview_count' => 9, 'is_bot' => true]);

    $counts = $this->engagement->headlineCounts($this->period, null);

    expect($counts['sessions'])->toBe(4)
        ->and($counts['visitors'])->toBe(4)
        ->and($counts['pageviews'])->toBe(7)
        ->and($counts['bounces'])->toBe(1)
        ->and((int) round($counts['avgSeconds']))->toBe(15); // (0 + 0 + 0 + 60) / 4
});

it('narrows the raw headline counts to a subject type', function () {
    $client = makeDashboardVisitor();
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => 1], $client);
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => 1], $client);
    makeDashboardSession(['subject_type' => 'lessor', 'subject_id' => 7]);

    $counts = $this->engagement->headlineCounts($this->period, 'client');

    expect($counts['sessions'])->toBe(2)
        ->and($counts['visitors'])->toBe(1);
});

it('fetches raw today and yesterday counts for the spotlight', function () {
    makeDashboardSession();
    makeDashboardSession();
    makeDashboardSession(['started_at' => now()->subDay(), 'last_activity_at' => now()->subDay()]);

    $spotlight = $this->engagement->spotlightCounts(null);

    expect($spotlight['today']['sessions'])->toBe(2)
        ->and($spotlight['today']['visitors'])->toBe(2)
        ->and($spotlight['yesterday']['sessions'])->toBe(1);
});

it('fetches raw daily trend rows keyed by day, without zero-fill', function () {
    makeDashboardSession(['pageview_count' => 1]);
    makeDashboardSession(['pageview_count' => 1]);
    makeDashboardSession(['pageview_count' => 4, 'started_at' => now()->subDays(2)]);

    $rows = $this->overview->trendRows(Period::ofDays(7), null);

    expect($rows[now()->format('Y-m-d')])->toBe(['sessions' => 2, 'pageviews' => 2])
        ->and($rows[now()->subDays(2)->format('Y-m-d')]['pageviews'])->toBe(4)
        ->and($rows)->not->toHaveKey(now()->subDay()->format('Y-m-d'));
});

it('fetches raw daily sparkline rows keyed by day', function () {
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subDays(2), 'last_activity_at' => now()->subDays(2)]);

    $rows = $this->engagement->sparklineRows(Period::ofDays(7), null);

    expect($rows[now()->format('Y-m-d')])->toMatchArray(['sessions' => 2, 'visitors' => 2, 'pageviews' => 4, 'bounces' => 0])
        ->and($rows)->toHaveKey(now()->subDays(2)->format('Y-m-d'));
});

it('ranks the top sources with previous-period counts', function () {
    makeDashboardSession(['source' => 'google']);
    makeDashboardSession(['source' => 'google']);
    makeDashboardSession(['source' => 'facebook']);
    makeDashboardSession(['source' => 'google', 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);

    expect($this->overview->topSources($this->period, null))->toBe([
        ['label' => 'google', 'total' => 2, 'previous' => 1],
        ['label' => 'facebook', 'total' => 1, 'previous' => 0],
    ]);
});

it('reclassifies campaign-matched sessions as paid in the channel breakdown', function () {
    Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);

    // Two organic-classified sessions that actually match the campaign, plus one that does not.
    makeDashboardSession(['source' => 'organic', 'mkt_params' => ['src' => 'meta_ete']]);
    makeDashboardSession(['source' => 'organic', 'mkt_params' => ['src' => 'meta_ete']]);
    makeDashboardSession(['source' => 'organic']);

    $sources = collect($this->overview->topSources($this->period, null))->pluck('total', 'label');

    expect($sources['paid'] ?? 0)->toBe(2)
        ->and($sources['organic'] ?? 0)->toBe(1);
});

it('breaks down events by name and flags declared conversions', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Click, ['name' => 'Lead']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'Lead']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact']);

    $registry = new EventRegistry;
    $registry->register(new TrackedEvent('Lead', 'Demande de code', 3.0));

    $breakdown = (new EventReadRepository)->eventBreakdown($this->period, null, $registry);

    expect($breakdown)->toHaveCount(2)
        ->and($breakdown[0])->toMatchArray(['name' => 'Lead', 'label' => 'Demande de code', 'count' => 2, 'isConversion' => true, 'valueTotal' => 6.0])
        ->and($breakdown[1])->toMatchArray(['name' => 'cta.contact', 'count' => 1, 'isConversion' => false]);
});

it('counts named events and conversions per session in the list', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'Lead']);
    makeDashboardEvent($session, EventType::Pageview); // unnamed → excluded

    $result = (new SessionListReadRepository)->paginateSessions(
        $this->period, null, null, null, null, app(SubjectResolver::class), conversionNames: ['Lead'],
    );
    $row = $result->first();

    expect((int) $row->events_count)->toBe(2)
        ->and((int) $row->conversions_count)->toBe(1);
});

it('ranks the top localities (country + city)', function () {
    makeDashboardSession(['country' => 'FR', 'city' => 'Paris']);
    makeDashboardSession(['country' => 'FR', 'city' => 'Paris']);
    makeDashboardSession(['country' => 'FR', 'city' => 'Lyon']);
    makeDashboardSession(['country' => 'CH', 'city' => 'Genève']);

    $localities = $this->overview->topLocalities($this->period, null);

    expect($localities)->toHaveCount(3)
        ->and($localities[0])->toBe(['country' => 'FR', 'city' => 'Paris', 'total' => 2, 'previous' => 0]);
});

it('ranks the most viewed pages by their real URL, excluding bot sessions', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'listing.detail', 'url' => 'https://x.test/listings/25']);
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'listing.detail', 'url' => 'https://x.test/listings/25']);
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'catalog', 'url' => 'https://x.test/catalog']);

    $bot = makeDashboardSession(['is_bot' => true]);
    makeDashboardEvent($bot, EventType::Pageview, ['route' => 'home', 'url' => 'https://x.test/']);

    expect($this->overview->topPages($this->period, null))->toBe([
        ['label' => 'https://x.test/listings/25', 'total' => 2, 'previous' => 0], // same URL aggregates
        ['label' => 'https://x.test/catalog', 'total' => 1, 'previous' => 0],
    ]);
});

it('ranks the top clicks, preferring the visible text over the technical name', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'auth.login', 'route' => 'client.login']);

    expect($this->overview->topClicks($this->period, null))->toBe([
        ['label' => 'Nous contacter', 'route' => 'home', 'total' => 2],
        ['label' => 'auth.login', 'route' => 'client.login', 'total' => 1],
    ]);
});

it('splits new and returning visitor counts', function () {
    makeDashboardSession();

    $returning = makeDashboardVisitor();
    $returning->update(['first_seen_at' => now()->subMonths(3)]);
    makeDashboardSession([], $returning);

    expect($this->overview->newVsReturning($this->period, null))->toBe(['new' => 1, 'returning' => 1]);
});

it('counts sessions by device type, busiest first', function () {
    makeDashboardSession(['device_type' => 'desktop']);
    makeDashboardSession(['device_type' => 'desktop']);
    makeDashboardSession(['device_type' => 'mobile']);
    makeDashboardSession(['device_type' => null]);

    expect($this->overview->sessionsByDevice($this->period, null))->toBe(['desktop' => 2, 'mobile' => 1]);
});

it('paginates sessions newest first, excluding bots, with filters', function () {
    foreach (range(1, 22) as $i) {
        makeDashboardSession(['city' => 'Paris', 'device_type' => 'desktop', 'started_at' => now()->subMinutes($i)]);
    }
    makeDashboardSession(['city' => 'Geneva', 'device_type' => 'mobile']);
    makeDashboardSession(['city' => 'Paris', 'is_bot' => true]);

    $subjects = new SubjectResolver;
    $all = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects);
    $byCity = $this->sessions->paginateSessions($this->period, null, 'Geneva', null, null, $subjects);
    $byDevice = $this->sessions->paginateSessions($this->period, null, null, 'mobile', null, $subjects);

    expect($all->total())->toBe(23)
        ->and($all->count())->toBe(20)
        ->and($byCity->total())->toBe(1)
        ->and($byDevice->total())->toBe(1)
        ->and($byDevice->first()->city)->toBe('Geneva');
});

it('sorts sessions by a whitelisted column and direction', function () {
    makeDashboardSession(['pageview_count' => 3]);
    makeDashboardSession(['pageview_count' => 9]);
    makeDashboardSession(['pageview_count' => 1]);

    $subjects = new SubjectResolver;
    $asc = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects, 'pageview_count', 'asc');
    $desc = $this->sessions->paginateSessions($this->period, null, null, null, null, $subjects, 'pageview_count', 'desc');

    expect($asc->first()->pageview_count)->toBe(1)
        ->and($desc->first()->pageview_count)->toBe(9);
});

it('searches sessions by visitor name resolved from the guard model', function () {
    config()->set('analytics.identity.subjects.client', ['label' => 'Client', 'name' => ['first_name', 'last_name']]);

    $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => $marie->id]);
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => 999]);

    $found = $this->sessions->paginateSessions($this->period, null, 'Marie', null, null, new SubjectResolver);

    expect($found->total())->toBe(1)
        ->and($found->first()->subject_id)->toBe($marie->id);
});

it('searches sessions by the visitor uuid shown as the ID', function () {
    $visitor = Visitor::create(['uuid' => 'vd-known-42', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    makeDashboardSession([], $visitor);
    makeDashboardSession();

    $found = $this->sessions->paginateSessions($this->period, null, 'vd-known', null, null, new SubjectResolver);

    expect($found->total())->toBe(1)
        ->and($found->first()->visitor_id)->toBe($visitor->id);
});

it('searches sessions by country name, resolving the stored ISO code', function () {
    makeDashboardSession(['country' => 'FR']);
    makeDashboardSession(['country' => 'CH']);

    $found = $this->sessions->paginateSessions($this->period, null, 'France', null, null, new SubjectResolver);

    expect($found->total())->toBe(1)
        ->and($found->first()->country)->toBe('FR');
});

it('paginates visitors active in the period with their derived columns', function () {
    $visitor = makeDashboardVisitor();
    makeDashboardSession(['source' => 'organic', 'started_at' => now()->subDays(60)], $visitor); // first-ever = acquisition
    makeDashboardSession(['city' => 'Lyon', 'source' => 'referral', 'started_at' => now()->subDays(3)], $visitor);
    makeDashboardSession(['city' => 'Paris', 'source' => 'paid', 'started_at' => now()->subDay()], $visitor); // latest = locality

    makeDashboardSession(['is_bot' => true], makeDashboardVisitor()); // bot-only visitor: excluded

    $result = $this->visitors->paginateVisitors($this->period, null, null, new SubjectResolver);

    expect($result->total())->toBe(1);

    $row = $result->first();
    expect((int) $row->id)->toBe($visitor->id)
        ->and((int) $row->period_sessions)->toBe(2)       // only the two in-period sessions
        ->and($row->last_city)->toBe('Paris')             // latest session
        ->and($row->acquisition_source)->toBe('organic'); // first-ever session
});

it('sorts visitors by their period session count', function () {
    $busy = makeDashboardVisitor();
    makeDashboardSession([], $busy);
    makeDashboardSession([], $busy);
    makeDashboardSession([], makeDashboardVisitor());

    $desc = $this->visitors->paginateVisitors($this->period, null, null, new SubjectResolver, 'period_sessions', 'desc');

    expect((int) $desc->first()->period_sessions)->toBe(2)
        ->and((int) $desc->first()->id)->toBe($busy->id);
});

it('counts period visitors, new visitors and sessions, excluding bots', function () {
    $returning = makeDashboardVisitor();
    $returning->update(['first_seen_at' => now()->subDays(60)]); // seen before the period
    makeDashboardSession([], $returning);
    makeDashboardSession([], $returning);

    $new = makeDashboardVisitor();
    $new->update(['first_seen_at' => now()->subDay()]); // first seen within the period
    makeDashboardSession([], $new);

    makeDashboardSession(['is_bot' => true], makeDashboardVisitor()); // bot: excluded

    $counts = $this->visitors->visitorCounts($this->period, null);

    expect($counts['visitors'])->toBe(2)
        ->and($counts['new'])->toBe(1)
        ->and($counts['sessions'])->toBe(3);
});

it('returns raw daily rows keyed by day with new visitors bot-excluded', function () {
    $visitor = makeDashboardVisitor();
    $visitor->update(['first_seen_at' => CarbonImmutable::parse('2026-06-10 09:00')]);
    makeDashboardSession(['started_at' => CarbonImmutable::parse('2026-06-10 09:00')], $visitor);

    $botVisitor = makeDashboardVisitor();
    $botVisitor->update(['first_seen_at' => CarbonImmutable::parse('2026-06-10 10:00')]);
    makeDashboardSession(['is_bot' => true, 'started_at' => CarbonImmutable::parse('2026-06-10 10:00')], $botVisitor);

    $rows = $this->visitors->visitorDailyRows($this->period, null);

    expect($rows['active']['2026-06-10'])->toBe(['sessions' => 1, 'visitors' => 1])
        ->and($rows['new']['2026-06-10'])->toBe(1); // bot-only visitor excluded from new
});

it('fetches the visitors screen data within its query budget', function () {
    foreach (range(1, 5) as $i) {
        makeDashboardSession(['city' => 'Paris']);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->visitors->paginateVisitors($this->period, null, null, new SubjectResolver);
    $this->visitors->visitorCounts($this->period, null);
    $this->visitors->visitorCounts($this->period->previous(), null);
    $this->visitors->visitorDailyRows($this->period, null);

    $queries = DB::getQueryLog();
    $signatures = array_map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']), $queries);

    // Frozen plan: paginate (count + select) + counts x2 (totals + new) + daily (active + new) = 8.
    expect($queries)->toHaveCount(8)
        ->and($signatures)->toBe(array_values(array_unique($signatures))); // nothing fetched twice
});

it('aggregates a visitor engagement over all their sessions and scopes to them', function () {
    $visitor = makeDashboardVisitor();
    makeDashboardSession(['device_type' => 'mobile', 'source' => 'google', 'pageview_count' => 3], $visitor);
    makeDashboardSession(['device_type' => 'mobile', 'source' => 'google', 'pageview_count' => 2], $visitor);
    makeDashboardSession(['device_type' => 'desktop', 'source' => null, 'pageview_count' => 1], $visitor);

    // Another visitor's session must never leak into the aggregate.
    makeDashboardSession(['device_type' => 'tablet', 'source' => 'social', 'pageview_count' => 9]);

    $engagement = app(VisitorProfileReadRepository::class)->engagement($visitor->id);

    expect($engagement['sessions'])->toBe(3)
        ->and($engagement['pageviews'])->toBe(6)
        ->and($engagement['devices'])->toBe(['mobile' => 2, 'desktop' => 1])
        ->and($engagement['sources'])->toBe(['google' => 2, 'direct' => 1]);
});

it('paginates a visitor sessions list', function () {
    $visitor = makeDashboardVisitor();
    foreach (range(1, 25) as $i) {
        makeDashboardSession(['started_at' => now()->subMinutes($i)], $visitor);
    }

    $page = app(VisitorProfileReadRepository::class)->paginateSessions($visitor->id, 20);

    expect($page->total())->toBe(25)
        ->and($page->perPage())->toBe(20)
        ->and($page->count())->toBe(20);
});
