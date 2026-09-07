<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

/**
 * How a screen reaches the browser.
 *
 * Until 2026-09-07 each screen was a full-page Livewire component mounted by
 * `Route::livewire`, naming its own layout through `->layout()`. It now goes
 * route → controller → thin view → component, the way falcon/booking has always
 * done it, and the host layout is extended rather than filled as a slot.
 *
 * The suite's forty-four route assertions already prove the screens answer.
 * What they cannot see is where the screen lands, because they run against the
 * package's own shell. These do.
 */
final class ScreenMountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        config()->set('analytics.dashboard.layout', 'host-shell');
    }

    private function admin(): TestAdmin
    {
        return TestAdmin::create(['email' => 'admin@example.test']);
    }

    public function test_it_renders_the_screen_inside_the_section_of_the_host_layout(): void
    {
        /*
         * Both halves matter. The chrome alone would mean the section name is
         * wrong and the screen fell on the floor — silently, since Blade yields
         * an empty section without complaining. The screen alone would mean the
         * layout was never extended.
         *
         * The needle is taken from the body of the screen and nowhere else. The
         * screen's own name would not do: it is in the <title> too, so the
         * assertion held even with the section deliberately misnamed.
         */
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.overview'))
            ->assertOk()
            ->assertSee('chrome fourni par le gabarit')
            ->assertSee(__('Visiteurs et appareils'));
    }

    /**
     * The reason the conversion was worth doing.
     *
     * The error state used to carry a layout of its own, so a failed read
     * replaced the entire page. The controller decides the chrome now, and the
     * component only fills the section: what is lost is the panel, not the way
     * out of it.
     */
    public function test_it_keeps_the_host_chrome_when_the_screen_fails_to_read_its_data(): void
    {
        $admin = $this->admin();

        $this->withoutTable('falcon_analytics_sessions', function () use ($admin): void {
            $this->actingAs($admin, 'admin')
                ->get(route('analytics.sessions'))
                ->assertOk()
                ->assertSee(__('Données indisponibles'))
                ->assertSee('chrome fourni par le gabarit');
        });
    }

    /** The title is composed by the controller, before the screen renders. */
    public function test_it_lets_the_controller_name_the_browser_tab(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.realtime'))
            ->assertSee('<title>'.__('Temps réel').' · '.__('Analytics').'</title>', false);
    }

    /**
     * Two screens name the record they show, and that title moved out of the
     * component when the screens were converted. The suite visits both already,
     * but only asserts on the body — this is the half that changed hands.
     */
    public function test_it_lets_the_controller_name_the_tab_after_the_record_it_shows(): void
    {
        $campaign = Campaign::create([
            'name' => 'Été 2026',
            'platform' => 'Meta',
            'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('marketing.campaigns.show', $campaign))
            ->assertSee('<title>Été 2026 · '.__('Marketing').'</title>', false);
    }
}
