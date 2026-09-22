<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Admin\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\FunnelsContent;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * A declarations file that stops on an error is said where its loss shows.
 *
 * The file is read as far as the error and no further, so everything declared
 * after it vanishes from the screens. Without this, the screen that shows the
 * events, or the funnels, would look complete.
 */
final class ABrokenDeclarationFileIsSaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_events_screen_says_its_declarations_were_not_read_whole(): void
    {
        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events-broken.php']);
        $this->app->forgetInstance(EventRegistry::class);

        Livewire::test(EventsContent::class, ['period' => 30])
            ->call('$refresh')
            ->assertSeeText("n'a pas pu être lu en entier");
    }

    public function test_the_funnels_screen_says_its_declarations_were_not_read_whole(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels-broken.php']);
        $this->app->forgetInstance(FunnelRegistry::class);

        Livewire::test(FunnelsContent::class, ['period' => 30])
            ->call('$refresh')
            ->assertSeeText("n'a pas pu être lu en entier");
    }

    public function test_a_file_read_whole_says_nothing(): void
    {
        config([
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
        ]);
        $this->app->forgetInstance(EventRegistry::class);
        $this->app->forgetInstance(FunnelRegistry::class);

        Livewire::test(EventsContent::class, ['period' => 30])->call('$refresh')->assertDontSeeText("n'a pas pu être lu");
        Livewire::test(FunnelsContent::class, ['period' => 30])->call('$refresh')->assertDontSeeText("n'a pas pu être lu");
    }
}
