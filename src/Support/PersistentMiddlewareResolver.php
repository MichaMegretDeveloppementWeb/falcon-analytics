<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * Turns the middleware stack an integrator configured into the class names
 * Livewire needs to reapply it on /livewire/update.
 *
 * Configuration holds whatever Laravel accepts, a group, an alias, an alias
 * with parameters or a class name; Livewire wants class strings.
 *
 * @internal
 */
final readonly class PersistentMiddlewareResolver
{
    /**
     * @param  array<string, class-string|string>  $aliases  the router's alias map
     * @param  array<string, list<string>>  $groups  the router's group map
     */
    public function __construct(
        private array $aliases,
        private array $groups,
    ) {}

    /**
     * @param  list<string>  $configured
     * @return list<class-string>
     */
    public function resolve(array $configured): array
    {
        $resolved = [];
        $alreadyApplied = $this->sessionStack();

        foreach ($this->flatten($configured) as $middleware) {
            // Arguments are stripped, as Livewire expects: this is an allow-list
            // matched on bare class names, not the stack that gets reapplied.
            $class = str_contains($middleware, ':')
                ? strstr($middleware, ':', before_needle: true)
                : $middleware;

            if ($class === false || $class === '') {
                continue;
            }

            $class = $this->aliases[$class] ?? $class;

            if (! class_exists($class)) {
                continue;
            }

            // Never re-declare what Livewire's own update route already runs:
            // running the session middleware twice destroys the session.
            if (isset($alreadyApplied[$class])) {
                continue;
            }

            $resolved[$class] = true;
        }

        /** @var list<class-string> */
        return array_keys($resolved);
    }

    /**
     * The classes Livewire's own update route already carries, keyed for lookup.
     *
     * @return array<string, true>
     */
    private function sessionStack(): array
    {
        $classes = [];

        foreach ($this->flatten(['web']) as $middleware) {
            $class = str_contains($middleware, ':')
                ? strstr($middleware, ':', before_needle: true)
                : $middleware;

            if ($class !== false && $class !== '') {
                $classes[$this->aliases[$class] ?? $class] = true;
            }
        }

        return $classes;
    }

    /**
     * Expands nested groups into a flat list, tracking visited names so a group
     * containing itself cannot loop.
     *
     * @param  list<string>  $middleware
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function flatten(array $middleware, array &$seen = []): array
    {
        $flat = [];

        foreach ($middleware as $entry) {
            if (! isset($this->groups[$entry])) {
                $flat[] = $entry;

                continue;
            }

            if (isset($seen[$entry])) {
                continue;
            }

            $seen[$entry] = true;

            foreach ($this->flatten($this->groups[$entry], $seen) as $expanded) {
                $flat[] = $expanded;
            }
        }

        return $flat;
    }
}
