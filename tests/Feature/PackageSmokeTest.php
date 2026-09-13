<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Falcon\Ui\Rendering\RenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The package stands up, end to end, in one render.
 *
 * **This file is the suite's common one**, named by the socle and kept in every
 * Falcon package. Its mechanism does not change from one package to the next ·
 * a real page, the kit's stylesheet and the package's on it, the marker saying
 * who drew it, and the render stack back where it started.
 *
 * **It leans on no other test, and that is the point.** Everything else here
 * proves a finer thing — that the sheet appears once and not twice, that the
 * route names have not moved, that every reactive view carries a root. Each of
 * those assumes the chain already stands. This one is what says it stands, and
 * a baseline that leaned on its neighbours would not be a baseline. The overlap
 * is deliberate and it is small.
 *
 * **What varies between packages is which page.** The model in the suite's
 * notice renders a public home page, which supposes every package has one.
 * Analytics has no public page at all · its public area is a collector that
 * receives a POST and answers no HTML. The render therefore happens on an
 * administration screen, the only area this package draws.
 */
final class PackageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_screen_answers_with_the_kit_and_the_package_on_it(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            ->assertSee('vendor/falcon/ui/ui.css', false)
            ->assertSee('vendor/falcon/analytics/analytics.css', false)
            ->assertSee('data-ui-scope="analytics"', false);
    }

    /**
     * And the render stack is back to zero · every opening was closed.
     *
     * **Nothing else in this package asks.** A stack left open does not show on
     * the page that opened it — it shows on the next one, where the kit reads a
     * scope belonging to a screen that finished rendering long ago, and dresses
     * it with a skin that is not its own. The failure appears one page away
     * from its cause, which is what makes it expensive.
     */
    public function test_the_render_stack_is_left_as_it_was_found(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.overview'))->assertSuccessful();

        $this->assertSame(0, app(RenderContext::class)->depth());
    }
}
