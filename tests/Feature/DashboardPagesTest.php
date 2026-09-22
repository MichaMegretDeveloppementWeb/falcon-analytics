<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Livewire\Admin\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\FunnelsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\TrendChart;
use Falcon\Analytics\Livewire\Admin\Widgets\VisitorsHeadline;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Support\SourceLabel;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

final class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->admin = TestAdmin::create([]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedSession(array $attributes = []): Session
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
        ], $attributes));
    }

    public function test_it_mounts_the_screens_at_the_configured_prefix_under_fixed_route_names(): void
    {
        $this->assertSame('/admin/analytics', route('analytics.admin.overview', absolute: false));
        $this->assertSame('/admin/analytics/visitors', route('analytics.admin.visitors', absolute: false));
        $this->assertSame('/admin/analytics/funnels', route('analytics.admin.funnels', absolute: false));
        $this->assertSame('/admin/analytics/events', route('analytics.admin.events', absolute: false));
        $this->assertSame('/admin/analytics/sessions', route('analytics.admin.sessions', absolute: false));
        $this->assertSame('/admin/analytics/sessions/1', route('analytics.admin.sessions.show', ['session' => 1], absolute: false));
        $this->assertSame('/admin/analytics/visitors/1', route('analytics.admin.visitors.show', ['visitor' => 1], absolute: false));
    }

    public function test_it_renders_the_events_screen_with_the_per_event_breakdown(): void
    {
        $session = $this->seedSession();

        Event::create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => EventType::Click,
            'name' => 'cta.contact',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin');

        Livewire::test(EventsContent::class, ['period' => 30])->call('$refresh')
            ->assertSuccessful()
            ->assertSeeText('cta.contact');
    }

    public function test_it_renders_the_events_content_within_its_query_budget(): void
    {
        $this->actingAs($this->admin, 'admin');

        // The deferred widget reads the breakdown (current and previous) and
        // the daily series; the page's shell emits no query at all.
        $budget = $this->assertCostIsFlat(
            function (): void {
                $session = $this->seedSession();
                Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'type' => EventType::Click, 'name' => 'cta.contact', 'occurred_at' => now()]);
            },
            fn () => Livewire::test(EventsContent::class, ['period' => 30])->call('$refresh'),
        );

        $this->assertLessThanOrEqual(6, $budget['count']);
    }

    public function test_it_renders_the_marketing_dashboard_content_within_its_query_budget(): void
    {
        Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

        $this->actingAs($this->admin, 'admin');

        // The deferred widget carries the heavy reads. The active campaigns and
        // ads, like the whole set of tagged sessions, are loaded once and
        // reused, so no read repeats itself.
        $budget = $this->assertCostIsFlat(
            fn () => $this->seedSession(['mkt_params' => ['src' => 'meta'], 'source' => 'social']),
            fn () => Livewire::test(MarketingDashboardContent::class, ['period' => 30])->call('$refresh'),
        );

        $this->assertLessThanOrEqual(12, $budget['count']);
    }

    public function test_it_renders_the_deferred_events_content_widget(): void
    {
        $this->seedSession();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(EventsContent::class, ['period' => 30, 'subject' => ''])->call('$refresh')->assertSuccessful();
    }

    public function test_it_protects_the_dashboard_from_guests(): void
    {
        $session = $this->seedSession();

        $this->get(route('analytics.admin.overview'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.visitors'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.funnels'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.sessions'))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.sessions.show', $session))->assertRedirect(route('login'));
        $this->get(route('analytics.admin.visitors.show', $session->visitor_id))->assertRedirect(route('login'));
    }

    public function test_it_renders_the_overview_digest_for_an_authenticated_admin(): void
    {
        $session = $this->seedSession(['source' => 'google']);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Pageview, 'route' => 'accueil', 'url' => 'https://vantadrive.test/accueil']);

        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.admin.overview'))->assertSuccessful()->assertSeeText(__('Vue d\'ensemble'));

        Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh')
            ->assertSeeText(__('Visiteurs'))
            ->assertSeeText(__('Durée moy. session'))
            ->assertSeeText(__('Taux de rebond'))
            ->assertSeeText(__('Pages vues'));
    }

    public function test_it_renders_the_deferred_overview_section_widgets_with_their_data(): void
    {
        $session = $this->seedSession(['source' => 'google', 'country' => 'FR', 'city' => 'Paris', 'device_type' => 'desktop']);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'type' => EventType::Pageview, 'route' => 'catalog', 'url' => 'https://x.test/catalog', 'occurred_at' => now()]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'type' => EventType::Click, 'name' => 'cta.contact', 'target_text' => 'Contact', 'occurred_at' => now()]);

        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewAudience::class, ['period' => 30])->call('$refresh')->assertSeeText(__('Appareils'));
        Livewire::test(OverviewAcquisition::class, ['period' => 30])->call('$refresh')->assertSeeText('Google');
        Livewire::test(OverviewContent::class, ['period' => 30])->call('$refresh')->assertSeeText('/catalog');
        Livewire::test(OverviewEvents::class, ['period' => 30])->call('$refresh')->assertSuccessful();
    }

    public function test_it_renders_a_graceful_error_state_instead_of_a_500_when_a_dashboard_read_fails(): void
    {
        $this->actingAs($this->admin, 'admin');

        // A real read is forced into failure: the session list falls on an
        // absent table, and the page's shell still reads, so its guarded render
        // degrades.
        $this->withoutTable('falcon_analytics_sessions', function (): void {
            Livewire::test(SessionsPage::class)->assertSee(__('Données indisponibles'));
        });
    }

    public function test_it_degrades_a_deferred_widget_to_an_inline_error_when_its_read_fails(): void
    {
        $this->actingAs($this->admin, 'admin');

        // The heavy reads live in the deferred widgets, which therefore carry
        // their own guard.
        $this->withoutTable('falcon_analytics_sessions', function (): void {
            Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh')->assertSee(__('Données indisponibles'));
        });
    }

    public function test_it_renders_the_sessions_list_for_an_authenticated_admin(): void
    {
        $this->seedSession(['city' => 'Genève', 'subject_type' => 'client', 'subject_id' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.admin.sessions'))
            ->assertSuccessful()
            ->assertSeeText(__('Sessions'))
            ->assertSeeText('Genève')
            ->assertSeeText('Client #1');
    }

    /** @return array<string, array{string}> */
    public static function listsOfVisitors(): array
    {
        return ['the sessions' => ['analytics.admin.sessions'], 'the visitors' => ['analytics.admin.visitors']];
    }

    /** A line shows the first eight characters of its visitor, and its button copies all of them. */
    #[DataProvider('listsOfVisitors')]
    public function test_a_line_shows_eight_characters_of_its_visitor_and_copies_the_whole_identifier(string $list): void
    {
        $uuid = Visitor::query()->findOrFail($this->seedSession()->visitor_id)->uuid;

        $this->actingAs($this->admin, 'admin')
            ->get(route($list))
            ->assertSuccessful()
            ->assertSee('x-data="anCopyList"', false)
            ->assertSee('<span class="an:font-mono">'.substr($uuid, 0, 8).'</span>', false)
            ->assertSee("copy('{$uuid}')", false)
            ->assertDontSeeText(substr($uuid, 0, 9));
    }

    public function test_the_source_filter_reads_a_channel_exactly_as_the_list_below_it_does(): void
    {
        $this->seedSession(['source' => 'organic']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.admin.sessions'))
            ->assertSuccessful()
            // The filter used to carry its own table of labels, so the same
            // channel read one way in the column and another in the select.
            ->assertSeeText(SourceLabel::for('organic'))
            ->assertDontSeeText('Naturel');
    }

    public function test_a_session_without_a_source_is_the_direct_channel_on_every_screen(): void
    {
        $this->seedSession([]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.admin.sessions'))
            ->assertSuccessful()
            ->assertSeeText(SourceLabel::for(null))
            ->assertDontSeeText('Directe');
    }

    public function test_it_renders_the_visitors_list_for_an_authenticated_admin(): void
    {
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

        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.admin.visitors'))->assertSuccessful()
            ->assertSeeText(__('Visiteurs'))
            ->assertSeeText('Client #1')
            ->assertSeeText('Genève');

        Livewire::test(VisitorsHeadline::class, ['period' => 30])->call('$refresh')
            ->assertSeeText(__('Nouveaux'))
            ->assertSeeText(__('Sessions / visiteur'));
    }

    public function test_it_renders_a_session_detail_with_its_information_and_event_timeline(): void
    {
        $session = $this->seedSession(['city' => 'Paris', 'subject_type' => 'client', 'subject_id' => 3, 'device_type' => 'desktop', 'browser' => 'Chrome']);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinutes(2), 'type' => EventType::Pageview, 'route' => 'catalog']);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Click, 'name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'catalog']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.admin.sessions.show', $session))
            ->assertSuccessful()
            ->assertSeeText(__('Parcours'))
            ->assertSee('Client #3')
            ->assertSeeText('Paris')
            ->assertSeeText('Chrome')
            ->assertSeeText('Nous contacter')
            ->assertSeeText('/catalog')
            ->assertSee(route('analytics.admin.visitors.show', $session->visitor_id, absolute: false));
    }

    public function test_it_renders_a_visitor_detail_with_its_sessions_engagement_and_breakdowns(): void
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'session_count' => 2,
        ]);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now()->addMinute(),
            'is_bot' => false,
            'pageview_count' => 3,
            'device_type' => 'mobile',
            'city' => 'Genève',
            'source' => 'google',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.admin.visitors.show', $visitor))
            ->assertSuccessful()
            ->assertSeeText(__('Visiteur #:id', ['id' => $visitor->id]))
            ->assertSeeText(__('Appareils'))
            ->assertSeeText(__('Acquisition'))
            ->assertSeeText('Genève')
            ->assertSee(route('analytics.admin.sessions.show', $session, absolute: false));
    }

    public function test_it_erases_only_the_target_visitor_leaving_other_visitors_untouched(): void
    {
        $make = function (): Visitor {
            $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'session_count' => 1]);
            $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => now(), 'last_activity_at' => now(), 'is_bot' => false, 'pageview_count' => 1]);
            Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => now(), 'type' => EventType::Pageview]);

            return $visitor;
        };

        $target = $make();
        $other = $make();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(VisitorDetailPage::class, ['visitor' => $target])
            ->call('forget')
            ->assertRedirect(route('analytics.admin.visitors'));

        // The target is erased entirely; the other visitor's data has to
        // survive, which proves the scoping.
        $this->assertFalse(Visitor::whereKey($target->id)->exists());
        $this->assertSame(0, Session::where('visitor_id', $target->id)->count());
        $this->assertSame(0, Event::where('visitor_id', $target->id)->count());
        $this->assertTrue(Visitor::whereKey($other->id)->exists());
        $this->assertSame(1, Session::where('visitor_id', $other->id)->count());
        $this->assertSame(1, Event::where('visitor_id', $other->id)->count());
    }

    // The erasure that fails lives in `TheErasureKeepsWhatItCannotDeleteTest`:
    // it needs a bench with no wrapping transaction, which this file cannot
    // offer without changing how its other tests run.

    public function test_it_renders_the_declared_funnels_for_an_authenticated_admin(): void
    {
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

        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.admin.funnels'))->assertSuccessful()->assertSeeText(__('Tunnels'));

        Livewire::test(FunnelsContent::class, ['period' => 30])->call('$refresh')->assertSeeText('Sample funnel');
    }

    public function test_it_defers_the_trend_chart_behind_a_skeleton_placeholder(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(TrendChart::class, ['period' => 30])
            ->assertSee('animate-pulse', escape: false);
    }

    public function test_it_renders_the_overview_headline_within_its_query_budget(): void
    {
        $this->actingAs($this->admin, 'admin');

        // The deferred widget carries the engagement reads; the shell emits
        // none. The counters (current and previous), the series and the
        // spotlight are each read once.
        $budget = $this->assertCostIsFlat(
            fn () => $this->seedSession(['source' => 'google', 'country' => 'FR', 'city' => 'Paris']),
            fn () => Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh'),
        );

        $this->assertLessThanOrEqual(8, $budget['count']);
    }

    public function test_it_renders_the_sessions_list_within_its_query_budget(): void
    {
        $this->actingAs($this->admin, 'admin');

        $budget = $this->assertCostIsFlat(
            fn () => $this->seedSession(['city' => 'Genève']),
            fn () => Livewire::test(SessionsPage::class),
        );

        $this->assertLessThanOrEqual(12, $budget['count']);
    }

    public function test_it_recomputes_the_overview_metrics_when_the_period_changes(): void
    {
        $this->seedSession();                                     // aujourd'hui, dans toutes les fenêtres
        $this->seedSession(['started_at' => now()->subDays(60)]); // only inside the 90-day window

        $this->actingAs($this->admin, 'admin');

        // The page swaps the deferred widget's period property, through
        // wire:key, so the widget recomputes per window.
        Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh')
            ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 1.0);

        Livewire::test(OverviewHeadline::class, ['period' => 90])->call('$refresh')
            ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 2.0);
    }

    public function test_it_filters_the_overview_metrics_by_subject_type(): void
    {
        $this->seedSession(['subject_type' => 'client', 'subject_id' => 1]);
        $this->seedSession(['subject_type' => 'lessor', 'subject_id' => 2]);

        $this->actingAs($this->admin, 'admin');

        Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh')
            ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 2.0);

        Livewire::test(OverviewHeadline::class, ['period' => 30, 'subject' => 'client'])->call('$refresh')
            ->assertViewHas('headline', fn ($headline) => $headline['sessions']->current === 1.0);
    }

    public function test_it_searches_and_resets_pagination_on_the_sessions_page(): void
    {
        $this->seedSession(['city' => 'Paris']);
        $this->seedSession(['city' => 'Genève']);

        $this->actingAs($this->admin, 'admin');

        // The chain is broken into three: `assertViewHas` comes from Laravel's
        // plugin, whose return type leads back to its own response class, and
        // `set()` does not exist there. At runtime the component returns
        // itself, but the tooling cannot know that.
        $component = Livewire::test(SessionsPage::class);

        $component->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 2);

        $component->set('search', 'Genève');

        $component->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 1);
    }
}
