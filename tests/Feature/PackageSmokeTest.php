<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Falcon\Ui\Rendering\RenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The package stands up, end to end · a real page, the kit's stylesheet and the
 * package's on it, the marker saying who drew it, and the render stack back
 * where it started.
 *
 * It is the baseline every other test assumes, so it leans on none of them.
 * What the page carries and what it leaves behind fail for unrelated reasons,
 * hence two tests. The page is an administration screen · the public area is a
 * collector that answers no HTML.
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
     * A stack left open does not show on the page that opened it but on the
     * next one, which the kit then dresses with a scope that is not its own.
     */
    public function test_the_render_stack_is_left_as_it_was_found(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.overview'))->assertSuccessful();

        $this->assertSame(0, app(RenderContext::class)->depth());
    }
}
