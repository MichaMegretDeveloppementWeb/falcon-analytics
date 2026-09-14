<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Maintenance;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * The one screen the retention changes, and what it says instead.
 *
 * Everything else reads the same before and after the erasing. A session's
 * step-by-step does not — it is what the retention is spent on — so this screen
 * is the only place the cost shows, and the only place it has to be explained.
 *
 * **The lie this replaces was the package's own.** A session whose detail had
 * gone read « Cette session n'a enregistré aucun évènement » · flatly false of
 * a session that recorded six, and contradicted on the same screen by the
 * figures just above it.
 *
 * Two absences, and they do not mean the same thing · a session that recorded
 * nothing, and a session whose record was erased. Telling them apart is the
 * whole of this essay.
 */
final class AnOldSessionSaysWhatItStillKnowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        config(['analytics.retention_days' => 30]);
    }

    private function anAdmin(): TestAdmin
    {
        return TestAdmin::create(['email' => 'admin@example.test']);
    }

    /**
     * A session with everything on it · pages, clicks, and a named event that
     * the erasing will spare.
     */
    private function aSessionOn(CarbonImmutable $day, bool $withEvents = true): Session
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => $day,
            'last_seen_at' => $day,
        ]);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $day->setTime(9, 0),
            'last_activity_at' => $day->setTime(9, 30),
            'is_bot' => false,
            // What the ingestion keeps as it happens; these rows are laid down
            // directly rather than through the endpoint.
            'pageview_count' => $withEvents ? 2 : 0,
            'click_count' => $withEvents ? 3 : 0,
            'event_count' => $withEvents ? 6 : 0,
        ]);

        if (! $withEvents) {
            return $session;
        }

        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => EventType::Pageview, 'url' => 'https://exemple.fr/', 'occurred_at' => $day->setTime(9, 0)]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => EventType::Pageview, 'url' => 'https://exemple.fr/tarifs', 'occurred_at' => $day->setTime(9, 5)]);

        foreach ([10, 11, 12] as $minute) {
            Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => EventType::Click, 'target_text' => 'Demander un devis', 'occurred_at' => $day->setTime(9, $minute)]);
        }

        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => EventType::Custom, 'name' => 'commande.payee', 'occurred_at' => $day->setTime(9, 20)]);

        return $session;
    }

    private function archiveThenPrune(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
    }

    /** @return TestResponse<Response> */
    private function open(Session $session): TestResponse
    {
        return $this->actingAs($this->anAdmin(), 'admin')
            ->get(route('analytics.admin.sessions.show', ['session' => $session->id]))
            ->assertSuccessful();
    }

    /**
     * The figures stay, and they are the session's own.
     *
     * Counting the rows that are left would answer zero clicks for a session
     * that had three · the counter was kept as it happened, so it outlives what
     * it counted.
     */
    public function test_it_still_gives_the_figures_after_the_erasing(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->assertSame(0, Event::query()->where('session_id', $old->id)->where('type', EventType::Click)->count(), 'The clicks really are gone.');

        /*
         * Asked of the component rather than of the page.
         *
         * The first draft looked for « 2 » and « 3 » in the HTML, which a page
         * of that size contains many times over · it passed with the counters
         * read back from the rows, which is exactly the defect it was meant to
         * refuse. A figure is worth asserting by name or not at all.
         */
        $this->actingAs($this->anAdmin(), 'admin');

        Livewire::test(SessionDetailPage::class, ['session' => $old])
            ->assertViewHas('clicksCount', 3)
            ->assertViewHas('eventsCount', 1)
            ->assertViewHas('conversionsCount', 0);

        $this->assertSame(2, $old->fresh()?->pageview_count);
    }

    /** And its named event, which the erasing spares. */
    public function test_it_still_shows_the_named_event(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->open($old)->assertSee('commande.payee');
    }

    /**
     * And it says why the step-by-step is thinner, rather than letting the
     * reader wonder.
     */
    public function test_it_says_the_step_by_step_was_erased(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->open($old)->assertSee('durée de conservation', false);
    }

    /**
     * A recent session says nothing of the sort.
     *
     * The notice has to be as absent when it does not apply as it is present
     * when it does · a screen that always explains itself explains nothing.
     */
    public function test_a_recent_session_carries_no_such_notice(): void
    {
        $recent = $this->aSessionOn(CarbonImmutable::now()->subDays(2));

        $this->archiveThenPrune();

        $this->open($recent)->assertDontSee('durée de conservation', false);
    }

    /**
     * And a session that genuinely recorded nothing keeps its own words.
     *
     * **This is the case the fix could have broken**, and it is the reason the
     * two absences are told apart rather than folded into one gentler sentence:
     * « aucun évènement » is true here, and saying « effacé » instead would be
     * the same lie in the other direction.
     */
    public function test_a_session_that_recorded_nothing_is_told_apart(): void
    {
        $empty = $this->aSessionOn(CarbonImmutable::now()->subDays(2), withEvents: false);

        $this->open($empty)
            ->assertSee(__('Aucun évènement'))
            ->assertDontSee(__('Détail effacé'));
    }

    /**
     * An old session that recorded nothing, on a day that WAS erased.
     *
     * **The first of three cases the archive register got wrong.** Its day is
     * marked as emptied — a neighbouring session had plenty to erase — so
     * asking the register « ce jour a-t-il été purgé ? » says yes, and the
     * screen announced a loss that never happened.
     *
     * What settles it is the session's own arithmetic · nothing counted,
     * nothing kept, nothing lost. The busy neighbour is there on purpose:
     * without it the erasing finds nothing to do and marks no day at all.
     */
    public function test_an_old_empty_session_is_not_said_to_be_erased_even_on_an_erased_day(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The neighbour, which is what gets the day marked as emptied.
        $this->aSessionOn($day);

        $empty = $this->aSessionOn($day, withEvents: false);

        $this->archiveThenPrune();

        $this->open($empty)
            ->assertSee(__('Aucun évènement'))
            ->assertDontSee(__('Détail effacé'));
    }

    /**
     * An old session whose every click carries a name keeps its step-by-step,
     * and is not told otherwise.
     *
     * **The second case, and the one that would have been seen most.** A host
     * that declares its conversions emits named clicks, and the erasing spares
     * every row that carries a name — so this visit is whole. The register
     * still answered « ce jour a été purgé » and the screen put « le détail a
     * été effacé » above a journey nobody had touched.
     */
    public function test_an_old_session_made_only_of_named_clicks_lost_nothing(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The neighbour again, so that the day really does get emptied.
        $this->aSessionOn($day);

        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => $day, 'last_seen_at' => $day]);

        $named = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $day->setTime(9, 0),
            'last_activity_at' => $day->setTime(9, 30),
            'is_bot' => false,
            'pageview_count' => 0,
            'click_count' => 2,
            'event_count' => 2,
        ]);

        foreach ([10, 12] as $minute) {
            Event::create([
                'session_id' => $named->id,
                'visitor_id' => $visitor->id,
                'type' => EventType::Click,
                'name' => 'devis.demande',
                'target_text' => 'Demander un devis',
                'occurred_at' => $day->setTime(9, $minute),
            ]);
        }

        $this->archiveThenPrune();

        $this->assertSame(2, Event::query()->where('session_id', $named->id)->count(), 'Named clicks are spared.');

        $this->open($named)->assertDontSee('durée de conservation', false);
    }

    /**
     * And the third · an old session whose pages all sit on a route a declared
     * funnel protects. The erasing leaves those rows where they are, so here
     * too the journey is whole and the screen must say nothing.
     */
    public function test_an_old_session_on_a_protected_route_lost_nothing(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The fixture funnel steps through the route `home`, which therefore
        // survives the erasing.
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(Maintenance::class);

        // The neighbour, so the day is emptied at all.
        $this->aSessionOn($day);

        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => $day, 'last_seen_at' => $day]);

        $protected = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $day->setTime(9, 0),
            'last_activity_at' => $day->setTime(9, 30),
            'is_bot' => false,
            'pageview_count' => 2,
            'click_count' => 0,
            'event_count' => 2,
        ]);

        foreach ([0, 5] as $minute) {
            Event::create([
                'session_id' => $protected->id,
                'visitor_id' => $visitor->id,
                'type' => EventType::Pageview,
                'url' => 'https://exemple.fr/panier',
                'route' => 'home',
                'occurred_at' => $day->setTime(9, $minute),
            ]);
        }

        $this->archiveThenPrune();

        $this->assertSame(2, Event::query()->where('session_id', $protected->id)->count(), 'A funnel route is spared.');

        $this->open($protected)->assertDontSee('durée de conservation', false);
    }
}
