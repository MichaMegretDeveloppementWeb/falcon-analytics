<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Dashboard\SessionsPage;
use Falcon\Analytics\Livewire\Dashboard\VisitorDetailPage;
use Falcon\Analytics\Livewire\Dashboard\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\FunnelsContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Dashboard\Widgets\TrendChart;
use Falcon\Analytics\Livewire\Dashboard\Widgets\VisitorsHeadline;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

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
     * Ce qu'un rendu coute en requetes, et combien il en repete.
     *
     * @return array{count: int, duplicates: int}
     */
    private function queryBudget(callable $render): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $render();

        $signatures = Collection::make(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_'))
            ->map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']));

        return [
            'count' => $signatures->count(),
            'duplicates' => $signatures->count() - $signatures->unique()->count(),
        ];
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

    public function test_it_mounts_the_dashboard_at_the_configured_prefix_and_route_names(): void
    {
        $this->assertSame('/admin/analytics', route('analytics.overview', absolute: false));
        $this->assertSame('/admin/analytics/visitors', route('analytics.visitors', absolute: false));
        $this->assertSame('/admin/analytics/funnels', route('analytics.funnels', absolute: false));
        $this->assertSame('/admin/analytics/events', route('analytics.events', absolute: false));
        $this->assertSame('/admin/analytics/sessions', route('analytics.sessions', absolute: false));
        $this->assertSame('/admin/analytics/sessions/1', route('analytics.sessions.show', ['session' => 1], absolute: false));
        $this->assertSame('/admin/analytics/visitors/1', route('analytics.visitors.show', ['visitor' => 1], absolute: false));
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
        $session = $this->seedSession();
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'type' => EventType::Click, 'name' => 'cta.contact', 'occurred_at' => now()]);

        $this->actingAs($this->admin, 'admin');

        // Le widget différé lit la ventilation (courante et précédente) et la
        // série quotidienne ; la coquille de la page n'émet aucune requête.
        $budget = $this->queryBudget(fn () => Livewire::test(EventsContent::class, ['period' => 30])->call('$refresh'));

        $this->assertLessThanOrEqual(6, $budget['count']);
        $this->assertSame(0, $budget['duplicates']);
    }

    public function test_it_renders_the_marketing_dashboard_content_within_its_query_budget(): void
    {
        Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $this->seedSession(['mkt_params' => ['src' => 'meta'], 'source' => 'social']);
        $this->seedSession(['mkt_params' => ['src' => 'meta'], 'source' => 'social']);

        $this->actingAs($this->admin, 'admin');

        // Le widget différé porte les lectures lourdes. Les campagnes et
        // publicités actives, comme l'ensemble des sessions taguées, sont
        // chargées une fois et réutilisées, donc aucune lecture ne se répète.
        $budget = $this->queryBudget(fn () => Livewire::test(MarketingDashboardContent::class, ['period' => 30])->call('$refresh'));

        $this->assertLessThanOrEqual(12, $budget['count']);
        $this->assertSame(0, $budget['duplicates']);
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

        $this->get(route('analytics.overview'))->assertRedirect(route('login'));
        $this->get(route('analytics.visitors'))->assertRedirect(route('login'));
        $this->get(route('analytics.funnels'))->assertRedirect(route('login'));
        $this->get(route('analytics.sessions'))->assertRedirect(route('login'));
        $this->get(route('analytics.sessions.show', $session))->assertRedirect(route('login'));
        $this->get(route('analytics.visitors.show', $session->visitor_id))->assertRedirect(route('login'));
    }

    public function test_it_renders_the_overview_digest_for_an_authenticated_admin(): void
    {
        $session = $this->seedSession(['source' => 'google']);
        Event::create(['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => now()->subMinute(), 'type' => EventType::Pageview, 'route' => 'accueil', 'url' => 'https://vantadrive.test/accueil']);

        $this->actingAs($this->admin, 'admin');

        $this->get(route('analytics.overview'))->assertSuccessful()->assertSeeText(__('Vue d\'ensemble'));

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

        // On force une vraie lecture en échec · la liste des sessions tombe sur
        // une table absente, et la coquille de la page lit encore, donc son
        // rendu gardé se dégrade.
        $this->withoutTable('falcon_analytics_sessions', function (): void {
            Livewire::test(SessionsPage::class)->assertSee(__('Données indisponibles'));
        });
    }

    public function test_it_degrades_a_deferred_widget_to_an_inline_error_when_its_read_fails(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Les lectures lourdes vivent dans les widgets différés, qui portent
        // donc leur propre garde.
        $this->withoutTable('falcon_analytics_sessions', function (): void {
            Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh')->assertSee(__('Données indisponibles'));
        });
    }

    public function test_it_renders_the_sessions_list_for_an_authenticated_admin(): void
    {
        $this->seedSession(['city' => 'Genève', 'subject_type' => 'client', 'subject_id' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('analytics.sessions'))
            ->assertSuccessful()
            ->assertSeeText(__('Sessions'))
            ->assertSeeText('Genève')
            ->assertSeeText('Client #1');
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

        $this->get(route('analytics.visitors'))->assertSuccessful()
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
            ->get(route('analytics.sessions.show', $session))
            ->assertSuccessful()
            ->assertSeeText(__('Parcours'))
            ->assertSee('Client #3')
            ->assertSeeText('Paris')
            ->assertSeeText('Chrome')
            ->assertSeeText('Nous contacter')
            ->assertSeeText('/catalog')
            ->assertSee(route('analytics.visitors.show', $session->visitor_id, absolute: false));
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
            ->get(route('analytics.visitors.show', $visitor))
            ->assertSuccessful()
            ->assertSeeText(__('Visiteur #:id', ['id' => $visitor->id]))
            ->assertSeeText(__('Appareils'))
            ->assertSeeText(__('Acquisition'))
            ->assertSeeText('Genève')
            ->assertSee(route('analytics.sessions.show', $session, absolute: false));
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
            ->assertRedirect(route('analytics.visitors'));

        // La cible est entièrement effacée ; les données de l'autre visiteur
        // doivent survivre, ce qui prouve le cadrage.
        $this->assertFalse(Visitor::whereKey($target->id)->exists());
        $this->assertSame(0, Session::where('visitor_id', $target->id)->count());
        $this->assertSame(0, Event::where('visitor_id', $target->id)->count());
        $this->assertTrue(Visitor::whereKey($other->id)->exists());
        $this->assertSame(1, Session::where('visitor_id', $other->id)->count());
        $this->assertSame(1, Event::where('visitor_id', $other->id)->count());
    }

    // L'effacement qui échoue vit dans `TheErasureKeepsWhatItCannotDeleteTest` ·
    // il lui faut un banc sans transaction enveloppante, ce que ce fichier ne
    // peut pas offrir sans changer la façon dont tournent ses autres essais.

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

        $this->get(route('analytics.funnels'))->assertSuccessful()->assertSeeText(__('Tunnels'));

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
        $this->seedSession(['source' => 'google', 'country' => 'FR', 'city' => 'Paris']);

        $this->actingAs($this->admin, 'admin');

        // Le widget différé porte les lectures d'engagement ; la coquille n'en
        // émet aucune. Les compteurs (courants et précédents), les séries et le
        // coup de projecteur sont lus une fois chacun.
        $budget = $this->queryBudget(fn () => Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh'));

        $this->assertLessThanOrEqual(8, $budget['count']);
        $this->assertSame(0, $budget['duplicates']);
    }

    public function test_it_renders_the_sessions_list_within_its_query_budget(): void
    {
        $this->seedSession(['city' => 'Genève']);

        $this->actingAs($this->admin, 'admin');

        $budget = $this->queryBudget(fn () => Livewire::test(SessionsPage::class));

        $this->assertLessThanOrEqual(12, $budget['count']);
        $this->assertSame(0, $budget['duplicates']);
    }

    public function test_it_recomputes_the_overview_metrics_when_the_period_changes(): void
    {
        $this->seedSession();                                     // aujourd'hui, dans toutes les fenêtres
        $this->seedSession(['started_at' => now()->subDays(60)]); // seulement dans la fenêtre de 90 jours

        $this->actingAs($this->admin, 'admin');

        // La page échange la propriété de période du widget différé, via
        // wire:key, donc le widget recalcule par fenêtre.
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

        Livewire::test(SessionsPage::class)
            ->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 2)
            ->set('search', 'Genève')
            ->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 1);
    }
}
