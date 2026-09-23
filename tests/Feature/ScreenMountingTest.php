<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

/**
 * How a screen reaches the browser · route, controller, thin view, component,
 * with the host layout extended.
 *
 * The route tests run against the package's own shell; these prove where a
 * screen lands under a host layout.
 */
final class ScreenMountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        config()->set('analytics.layouts.admin', 'host-shell');
    }

    private function admin(): TestAdmin
    {
        return TestAdmin::create(['email' => 'admin@example.test']);
    }

    /**
     * The chrome alone means a misnamed section, which Blade yields empty without
     * a word · the screen alone means a layout never extended. The needle comes
     * from the screen's body, since its name is also in the <title>.
     */
    public function test_it_renders_the_screen_inside_the_section_of_the_host_layout(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->assertSee('chrome fourni par le gabarit')
            ->assertSee(__('Visiteurs et appareils'));
    }

    /**
     * The package's utilities resolve kit tokens, `var(--ui-…)`, whose values
     * live in the kit's stylesheet. The fixture layout is bare, so the kit's
     * sheet comes from the kit components the screen draws, once.
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
     * The controller decides the chrome and the component only fills the
     * section, so a failed read loses the panel and not the way out of it.
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

    public function test_it_lets_the_controller_name_the_browser_tab(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.realtime'))
            ->assertSee('<title>'.__('Temps réel').' · '.__('Analytics').'</title>', false);
    }

    public function test_it_lets_the_controller_name_the_tab_after_the_record_it_shows(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Été 2026']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.marketing.campaigns.show', $campaign))
            ->assertSee('<title>Été 2026 · '.__('Marketing').'</title>', false);
    }

    /**
     * Blade escapes a class component's attributes when it lays them down, so a
     * title passed as one comes out of `{{ }}` escaped twice. The title travels
     * through the page's constructor, and this one carries an apostrophe.
     */
    public function test_it_escapes_the_title_once_and_not_twice(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSee('<title>Vue d&#039;ensemble · '.__('Analytics').'</title>', false)
            ->assertDontSee('&amp;#039;', false);
    }
}
