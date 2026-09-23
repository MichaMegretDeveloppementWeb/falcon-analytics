<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\Fixtures\Http\AsksForAStep;
use Falcon\Analytics\Tests\Fixtures\Http\EnsureTheHostAllows;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * What a host lays on a branch of the tree reaches every screen of that branch,
 * after the door and after the ability, and is replayed on every click.
 */
final class BranchMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['router']->aliasMiddleware('step.everywhere', EnsureTheHostAllows::class);
        $app['router']->aliasMiddleware('step.marketing', AsksForAStep::class);

        $app['config']->set('analytics.admin.middleware_for', [
            'analytics' => ['step.everywhere'],
            'analytics.marketing' => ['step.marketing'],
        ]);
    }

    public function test_the_steps_of_a_branch_follow_the_ability_and_accumulate_from_the_top(): void
    {
        $this->assertSame(
            ['web', 'auth', 'can:analytics.campaigns', 'step.everywhere', 'step.marketing'],
            $this->listedAfterTheDoor('analytics.admin.marketing.campaigns'),
        );
    }

    public function test_a_screen_outside_the_branch_receives_only_what_lies_above_it(): void
    {
        $this->assertSame(
            ['web', 'auth:admin', 'can:analytics.overview', 'step.everywhere'],
            $this->listedAfterTheDoor('analytics.admin.overview'),
        );
    }

    public function test_a_step_on_a_branch_is_taken_on_its_screens_and_nowhere_else(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.marketing.campaigns'))->assertRedirect('/step');
        $this->get(route('analytics.admin.overview'))->assertSuccessful();
    }

    public function test_the_steps_are_replayed_on_every_click(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains(AsksForAStep::class, $persistent);
        $this->assertContains(EnsureTheHostAllows::class, $persistent);
    }

    /** @return list<string> */
    private function listedAfterTheDoor(string $name): array
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route);

        return array_values(array_filter(
            $route->middleware(),
            fn (string $middleware): bool => ! str_contains($middleware, 'CatchesUpTheMaintenance'),
        ));
    }
}
