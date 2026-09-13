<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * The last degree of customisation the package offers, and the one with a cost.
 *
 * A layout dresses **every** screen alike. To put a banner or a breadcrumb on
 * one screen in particular, a host publishes that screen's thin view and edits
 * the three lines it contains — the body staying ours, inside the Livewire
 * component, so it keeps being updated.
 *
 * **Documented on 2026-09-13, and until then guarded by nothing.** The path was
 * offered by the service provider and mentioned nowhere; `docs/mise-a-jour.md`
 * even warned about the consequence of publishing a view without any page
 * saying it was possible. Written the moment the documentation started
 * promising it.
 *
 * @see ScreenMountingTest for the layer above ·
 *      the host layout, which dresses every screen at once.
 */
final class PublishingAViewWrapsTheScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The override is laid down before the application boots, and it has to be.
     *
     * **`loadViewsFrom` only registers the host's directory when it already
     * exists**, and it looks at boot. A directory created afterwards is not
     * consulted for the life of that application — which costs nothing in
     * production, where the next request boots afresh, and everything in a test
     * that publishes and then asks in the same breath.
     *
     * Written here rather than in `setUp`, which runs after the boot.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $override = $app->resourcePath('views/vendor/analytics/admin/dashboard/overview.blade.php');

        File::ensureDirectoryExists(dirname($override));
        File::put($override, <<<'BLADE'
            <x-analytics::page area="admin" :title="$analyticsTitle">
                <p>le bandeau de l'hôte</p>
                <livewire:analytics::admin.overview-page />
            </x-analytics::page>
            BLADE);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('views/vendor'));

        parent::tearDown();
    }

    /**
     * The tag lands where the documentation tells a host to look.
     *
     * **The address is the promise**, not the copying: an integrator told to
     * edit `resources/views/vendor/analytics/…` needs the file at exactly that
     * path, or the instruction wastes an afternoon.
     *
     * Read off the publish registry rather than by running the command, so that
     * the override this class lays down cannot answer in the real file's place.
     */
    public function test_the_tag_sends_the_views_where_a_host_can_find_them(): void
    {
        $paths = ServiceProvider::pathsToPublish(AnalyticsServiceProvider::class, 'analytics-views');

        $this->assertCount(1, $paths);

        [$source, $destination] = [array_key_first($paths), reset($paths)];

        $this->assertSame(resource_path('views/vendor/analytics'), $destination);
        $this->assertFileExists($source.'/admin/dashboard/overview.blade.php');
    }

    /**
     * What a host receives is a wrapper, not a screen.
     *
     * The three lines are the whole point · a host edits around the component
     * and the body stays ours, so it keeps being updated. A thin view that had
     * grown a screen's worth of markup would make this customisation a fork.
     */
    public function test_what_it_receives_is_three_lines_around_our_component(): void
    {
        $shipped = File::get(dirname(__DIR__, 2).'/resources/views/admin/dashboard/overview.blade.php');

        $this->assertStringContainsString('<x-analytics::page', $shipped);
        $this->assertStringContainsString('<livewire:analytics::admin.overview-page', $shipped);
        $this->assertLessThan(6, substr_count(trim($shipped), "\n") + 1, 'A thin view stopped being thin.');
    }

    /**
     * And a view laid there is the one that renders.
     *
     * Both halves matter · the marker alone would mean the override took over
     * and the screen fell on the floor; the screen alone would mean the
     * override was never read, and a host would be editing a file nothing opens.
     */
    public function test_a_published_view_is_the_one_that_renders(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->assertSee("le bandeau de l'hôte", false)
            // Taken from the body of the screen, which stays the package's.
            ->assertSee(__('Visiteurs et appareils'));
    }
}
