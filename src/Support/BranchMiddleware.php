<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Enums\Authorization\Ability;
use Illuminate\Routing\Route;

/**
 * The middleware a host lays on a branch of the ability tree
 * (`admin.middleware_for`), and the screens it reaches.
 *
 * A screen goes through the door, then its ability, then the middleware of
 * every branch it sits in, from the root of the tree down.
 *
 * @internal
 */
final readonly class BranchMiddleware
{
    /** @param array<array-key, mixed> $configured the map read whole, its keys holding dots */
    public function __construct(private array $configured) {}

    /**
     * What a screen asking this ability goes through after it.
     *
     * @return list<string>
     */
    public function for(Ability $ability): array
    {
        $steps = [];

        for ($level = $ability; $level !== null; $level = $level->parent()) {
            array_unshift($steps, ...$this->listedOn($level));
        }

        return $steps;
    }

    /**
     * Every middleware the map names, for Livewire to replay.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $named = [];

        foreach (array_keys($this->configured) as $key) {
            $ability = Ability::tryFrom((string) $key);

            if ($ability !== null) {
                array_push($named, ...$this->listedOn($ability));
            }
        }

        return array_values(array_unique($named));
    }

    /**
     * Adds its steps to every route that asks an ability of the package.
     *
     * @param  iterable<Route>  $routes
     */
    public function layOn(iterable $routes): void
    {
        foreach ($routes as $route) {
            $ability = self::abilityAskedBy($route);

            if ($ability !== null && ($steps = $this->for($ability)) !== []) {
                $route->middleware($steps);
            }
        }
    }

    /** The ability of the package a route asks through its `can:` middleware. */
    public static function abilityAskedBy(Route $route): ?Ability
    {
        foreach ($route->middleware() as $middleware) {
            if (str_starts_with($middleware, 'can:')) {
                return Ability::tryFrom(explode(',', substr($middleware, 4))[0]);
            }
        }

        return null;
    }

    /** @return list<string> */
    private function listedOn(Ability $ability): array
    {
        $listed = $this->configured[$ability->value] ?? [];

        return is_array($listed) ? array_values(array_filter($listed, is_string(...))) : [];
    }
}
