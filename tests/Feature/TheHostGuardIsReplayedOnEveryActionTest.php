<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\Fixtures\Http\EnsureTheHostAllows;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Session\Middleware\StartSession;
use Livewire\Livewire;

/**
 * The host's guard has to survive past the first page load.
 *
 * A screen's actions go to Livewire's own route, which reapplies only the
 * middleware registered as persistent, and compares them **by class**. A guard
 * a host names by an alias of its own, or reaches through a group, is therefore
 * replayed only if the package hands Livewire the class behind the name.
 *
 * Asserted on the registration rather than on a request · Livewire hides its
 * endpoint behind a hash and rejects a payload that names no component, so a
 * hand-made call would answer the same with or without the guard.
 */
final class TheHostGuardIsReplayedOnEveryActionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['router']->aliasMiddleware('ensure.host', EnsureTheHostAllows::class);
        $app['router']->middlewareGroup('host-marketing', ['ensure.host:marketing']);

        $app['config']->set('analytics.admin.middleware', ['web', 'ensure.host']);
        $app['config']->set('analytics.admin.marketing.middleware', ['web', 'host-marketing']);
    }

    public function test_a_guard_named_by_a_host_alias_is_replayed_by_its_class(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains(EnsureTheHostAllows::class, $persistent, 'Without it, the guard protects the first page and nothing after it.');
        $this->assertNotContains('ensure.host', $persistent, 'An alias never equals the class Livewire compares it with.');
    }

    public function test_a_guard_reached_through_a_host_group_is_replayed_by_its_class(): void
    {
        $this->assertNotContains('host-marketing', Livewire::getPersistentMiddleware());
        $this->assertContains(EnsureTheHostAllows::class, Livewire::getPersistentMiddleware());
    }

    /**
     * Livewire mounts its update route with the `web` group already · started
     * a second time, a session does not survive, and the administrator is
     * logged out in the middle of an action.
     */
    public function test_the_session_stack_is_left_to_livewire(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertNotContains(StartSession::class, $persistent);
        $this->assertNotContains('web', $persistent);
    }
}
