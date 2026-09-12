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

        config()->set('analytics.admin.layout', 'host-shell');
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
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->assertSee('chrome fourni par le gabarit')
            ->assertSee(__('Visiteurs et appareils'));
    }

    /**
     * The kit's stylesheet travels with the package's, even under a host layout
     * that knows nothing of the suite.
     *
     * **It is not decorative, it is necessary**: the package's utilities
     * resolve seven kit tokens at runtime, `var(--ui-…)`, and those values live
     * in the kit's stylesheet. Served on its own, the package's would display
     * without its colours.
     *
     * What brings it in is not the layout — the fixture's is bare — but the
     * screen's content, which draws kit components. That is what makes the
     * separation safe: a package's stylesheet cannot end up on a page without
     * the kit's.
     *
     * The day a screen uses no kit component at all, this test falls — and it
     * will then be a decision, not a discovery.
     */
    public function test_the_kit_sheet_travels_with_the_package_sheet(): void
    {
        $html = (string) $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->getContent();

        foreach (['ui.css', 'analytics.css'] as $sheet) {
            $this->assertSame(1, substr_count($html, $sheet), "{$sheet} has to appear once.");
        }
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
                ->get(route('analytics.admin.sessions'))
                ->assertOk()
                ->assertSee(__('Données indisponibles'))
                ->assertSee('chrome fourni par le gabarit');
        });
    }

    /** The title is composed by the controller, before the screen renders. */
    public function test_it_lets_the_controller_name_the_browser_tab(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.realtime'))
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
            ->get(route('analytics.admin.marketing.campaigns.show', $campaign))
            ->assertSee('<title>Été 2026 · '.__('Marketing').'</title>', false);
    }

    /**
     * The title crosses two components, and it has to come out whole.
     *
     * **Blade escapes a class component's attributes at the moment it lays them
     * down**, because an attribute ends up inside a tag. A title passed that
     * way reached the layout already escaped and came out of `{{ }}` escaped a
     * second time: `Vue d&amp;#039;ensemble` in the browser tab, while the rest
     * of the page was fine.
     *
     * It therefore travels through the page's constructor, where it is data.
     * The two tests above would never have seen it: none of their titles
     * carries an apostrophe.
     */
    public function test_it_escapes_the_title_once_and_not_twice(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSee('<title>Vue d&#039;ensemble · '.__('Analytics').'</title>', false)
            ->assertDontSee('&amp;#039;', false);
    }
}
