<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Session\SessionDetail;
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
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * The one screen the retention changes, and what it says instead.
 *
 * A session's step-by-step is what the retention erases, so this screen is the
 * only place the cost shows. It tells two absences apart: a session that
 * recorded nothing, and a session whose detail was erased.
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
        $session = $this->aVisitOn($day, [
            // The counters ingestion keeps, set here since these rows skip the endpoint.
            'pageview_count' => $withEvents ? 2 : 0,
            'click_count' => $withEvents ? 3 : 0,
            'event_count' => $withEvents ? 6 : 0,
        ]);

        if (! $withEvents) {
            return $session;
        }

        Event::factory()->for($session)->create(['url' => 'https://exemple.test/', 'occurred_at' => $day->setTime(9, 0)]);
        Event::factory()->for($session)->create(['url' => 'https://exemple.test/tarifs', 'occurred_at' => $day->setTime(9, 5)]);

        foreach ([10, 11, 12] as $minute) {
            Event::factory()->for($session)->click(text: 'Demander un devis')->create(['occurred_at' => $day->setTime(9, $minute)]);
        }

        Event::factory()->for($session)->custom('commande.payee')->create(['occurred_at' => $day->setTime(9, 20)]);

        return $session;
    }

    /**
     * A visit from 9:00 to 9:30 that day, by a visitor first seen then.
     *
     * @param  array<string, int>  $counters
     */
    private function aVisitOn(CarbonImmutable $day, array $counters): Session
    {
        return Session::factory()
            ->for(Visitor::factory()->state(['first_seen_at' => $day, 'last_seen_at' => $day]))
            ->create(['started_at' => $day->setTime(9, 0), 'last_activity_at' => $day->setTime(9, 30), ...$counters]);
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
     * The session's counters outlive the rows they counted: counting what is
     * left would answer zero clicks.
     */
    public function test_it_still_gives_the_figures_after_the_erasing(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->assertSame(0, Event::query()->where('session_id', $old->id)->where('type', EventType::Click)->count(), 'The clicks really are gone.');

        // Asked of the component: a bare figure occurs many times in the page's HTML.
        $this->actingAs($this->anAdmin(), 'admin');

        $detail = Livewire::test(SessionDetailPage::class, ['session' => $old])->viewData('detail');

        $this->assertInstanceOf(SessionDetail::class, $detail);
        $this->assertSame(3, $detail->clicksCount);
        $this->assertSame(1, $detail->eventsCount);
        $this->assertSame(0, $detail->conversionsCount);

        $this->assertSame(2, $old->fresh()?->pageview_count);
    }

    /** The erasing spares named events. */
    public function test_it_still_shows_the_named_event(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->open($old)->assertSee('commande.payee');
    }

    public function test_it_says_the_step_by_step_was_erased(): void
    {
        $old = $this->aSessionOn(CarbonImmutable::parse('2026-03-01'));

        $this->archiveThenPrune();

        $this->open($old)->assertSee('durée de conservation', false);
    }

    public function test_a_recent_session_carries_no_such_notice(): void
    {
        $recent = $this->aSessionOn(CarbonImmutable::now()->subDays(2));

        $this->archiveThenPrune();

        $this->open($recent)->assertDontSee('durée de conservation', false);
    }

    /** Here « aucun événement » is true, and « effacé » would be false. */
    public function test_a_session_that_recorded_nothing_is_told_apart(): void
    {
        $empty = $this->aSessionOn(CarbonImmutable::now()->subDays(2), withEvents: false);

        $this->open($empty)
            ->assertSee(__('Aucun événement'))
            ->assertDontSee(__('Détail effacé'));
    }

    /**
     * The archive register marks the day as emptied, so only the session's own
     * counters tell that nothing was lost: nothing counted, nothing kept.
     */
    public function test_an_old_empty_session_is_not_said_to_be_erased_even_on_an_erased_day(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The neighbour, which is what gets the day marked as emptied.
        $this->aSessionOn($day);

        $empty = $this->aSessionOn($day, withEvents: false);

        $this->archiveThenPrune();

        $this->open($empty)
            ->assertSee(__('Aucun événement'))
            ->assertDontSee(__('Détail effacé'));
    }

    /**
     * The erasing spares every row that carries a name, so this visit is whole
     * although the register marks its day as emptied.
     */
    public function test_an_old_session_made_only_of_named_clicks_lost_nothing(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The neighbour, which gets the day marked as emptied.
        $this->aSessionOn($day);

        $named = $this->aVisitOn($day, ['pageview_count' => 0, 'click_count' => 2, 'event_count' => 2]);

        foreach ([10, 12] as $minute) {
            Event::factory()->for($named)->click('devis.demande', 'Demander un devis')->create(['occurred_at' => $day->setTime(9, $minute)]);
        }

        $this->archiveThenPrune();

        $this->assertSame(2, Event::query()->where('session_id', $named->id)->count(), 'Named clicks are spared.');

        $this->open($named)->assertDontSee('durée de conservation', false);
    }

    /** The erasing spares rows on a route a declared funnel steps through, so the journey is whole. */
    public function test_an_old_session_on_a_protected_route_lost_nothing(): void
    {
        $day = CarbonImmutable::parse('2026-03-01');

        // The fixture funnel steps through `home`, so that route survives the erasing.
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(Maintenance::class);

        // The neighbour, which gets the day marked as emptied.
        $this->aSessionOn($day);

        $protected = $this->aVisitOn($day, ['pageview_count' => 2, 'click_count' => 0, 'event_count' => 2]);

        foreach ([0, 5] as $minute) {
            Event::factory()->for($protected)->create([
                'url' => 'https://exemple.test/panier',
                'route' => 'home',
                'occurred_at' => $day->setTime(9, $minute),
            ]);
        }

        $this->archiveThenPrune();

        $this->assertSame(2, Event::query()->where('session_id', $protected->id)->count(), 'A funnel route is spared.');

        $this->open($protected)->assertDontSee('durée de conservation', false);
    }
}
