<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Admin\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * The kit draws for someone, and that someone has to be named.
 *
 * A kit button laid on an analytics screen carries `data-ui-scope` and
 * `data-ui-area`. That is what the package's stylesheet targets, and also what
 * lets the kit reach for a skin belonging to the package instead of its own
 * view. None of it is derived from the URL or from a composer: the kit reads a
 * stack, during the render, and that stack only exists if a tag opened it.
 *
 * **The second test is the one that justifies the root.** A reactive view is
 * recomputed on its own after a click, without the page that drew it. A context
 * laid by the page alone would be there on opening and gone on the next click:
 * the appearance would change after an interaction, with no error anywhere.
 */
final class TheKitKnowsWhoItDrawsForTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_screen_draws_the_kit_inside_the_context(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            ->assertSee('data-ui-scope="analytics"', false)
            ->assertSee('data-ui-area="admin"', false);
    }

    /**
     * `Livewire::test` renders the component for itself, like a response to a
     * click: if the context came from the page alone, this output would be bare.
     */
    public function test_a_recomputed_fragment_keeps_the_context(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Livewire::withoutLazyLoading();

        $html = Livewire::test(OverviewSearchQueries::class)->html();

        $this->assertStringContainsString('data-ui-scope="analytics"', $html);
        $this->assertStringContainsString('data-ui-area="admin"', $html);
    }

    /**
     * The page and every root in it declare the stylesheet, and the kit folds
     * them into one tag. The kit's sheet comes first, as the layer contract
     * assumes · read in the wrong order, empty layers invert the priority.
     */
    public function test_a_screen_serves_the_package_sheet_exactly_once(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $html = (string) $this->get(route('analytics.admin.overview'))->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'analytics/analytics.css'),
            "The package's stylesheet must appear once and once only.",
        );

        $this->assertLessThan(
            strpos($html, 'analytics/analytics.css'),
            strpos($html, 'ui/ui.css'),
            "The kit's stylesheet is read before the package's.",
        );
    }

    /** The list is read off the components, so a new view enters the check on its own. */
    public function test_every_view_a_component_returns_carries_a_root(): void
    {
        $without = [];

        foreach (self::viewsReturnedByComponents() as $view) {
            $path = __DIR__.'/../../resources/views/'
                .str_replace('.', '/', substr($view, strlen('analytics::')))
                .'.blade.php';

            $this->assertFileExists($path, "The component renders {$view}, which does not exist.");

            if (! str_contains(File::get($path), '<x-analytics::root')) {
                $without[] = $view;
            }
        }

        $this->assertNotSame([], self::viewsReturnedByComponents(), 'No view collected: the collection is broken.');
        $this->assertSame([], $without, 'These views will be drawn out of context after an interaction.');
    }

    /**
     * The views a reactive component can render: screens, blocks, waiting
     * templates and error views alike.
     *
     * @return list<string>
     */
    private static function viewsReturnedByComponents(): array
    {
        $names = [];

        foreach (File::allFiles(__DIR__.'/../../src/Livewire') as $file) {
            preg_match_all("/view\('(analytics::[^']+)'/", File::get($file->getPathname()), $matches);

            $names = [...$names, ...$matches[1]];
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
