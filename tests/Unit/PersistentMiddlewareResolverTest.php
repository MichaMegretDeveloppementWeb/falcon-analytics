<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\PersistentMiddlewareResolver;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use PHPUnit\Framework\TestCase;

final class PersistentMiddlewareResolverTest extends TestCase
{
    private function resolver(): PersistentMiddlewareResolver
    {
        return new PersistentMiddlewareResolver(
            aliases: [
                'auth' => Authenticate::class,
                'inexistant' => 'App\\Http\\Middleware\\QuiNExistePas',
            ],
            groups: [
                'web' => ['cookies', StartSession::class],
                'cookies' => [AddQueuedCookiesToResponse::class],
                'boucle' => ['boucle', StartSession::class],
            ],
        );
    }

    public function test_an_alias_becomes_its_class(): void
    {
        $this->assertSame([Authenticate::class], $this->resolver()->resolve(['auth']));
    }

    /**
     * The list is an allow-list, not the stack that is replayed. Livewire
     * matches on the bare class name and reapplies the route's own entry, guard
     * included, so registering the bare class is what lets auth:admin survive.
     */
    public function test_a_parameterised_middleware_is_registered_by_its_bare_class(): void
    {
        $this->assertSame([Authenticate::class], $this->resolver()->resolve(['auth:admin']));
    }

    /**
     * Livewire mounts its update route with the 'web' group, so re-declaring that
     * group makes it run the session stack a second time on a reconstructed
     * request. Starting an already-started session destroys it, logging the user
     * out mid-action after a successful write, and persistent middleware is
     * global, so this reaches every component in the host.
     */
    public function test_the_web_group_is_never_registered_because_livewire_already_applies_it(): void
    {
        $this->assertSame([], $this->resolver()->resolve(['web']));
    }

    public function test_only_what_the_host_adds_on_top_of_web_is_registered(): void
    {
        $this->assertSame(
            [Authenticate::class],
            $this->resolver()->resolve(['web', 'auth:admin']),
        );
    }

    public function test_a_session_middleware_named_directly_is_excluded_too(): void
    {
        $this->assertSame([], $this->resolver()->resolve([StartSession::class]));
    }

    public function test_a_group_that_contains_itself_does_not_loop_forever(): void
    {
        $this->assertSame([], $this->resolver()->resolve(['boucle']));
    }

    public function test_a_name_that_resolves_to_nothing_is_skipped_rather_than_fatal(): void
    {
        $this->assertSame(
            [Authenticate::class],
            $this->resolver()->resolve(['inexistant', 'auth', 'pas-un-middleware']),
            'A bad configuration entry must not stop the package from booting.'
        );
    }

    public function test_an_empty_stack_resolves_to_nothing(): void
    {
        $this->assertSame([], $this->resolver()->resolve([]));
    }
}
