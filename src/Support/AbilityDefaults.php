<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Closure;
use Falcon\Analytics\Enums\Authorization\Ability;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gives every ability the host left undefined the answer of the one above it,
 * and the root the answer "any signed-in account".
 *
 * Filled once the whole application has booted, so a rule the host writes in
 * any provider is found first and kept.
 *
 * @internal
 */
final class AbilityDefaults
{
    /** @var array<string, Closure> */
    private array $defaults = [];

    public function __construct(private readonly Gate $gate) {}

    public function fill(): void
    {
        foreach (Ability::cases() as $ability) {
            if ($this->gate->has($ability->value)) {
                continue;
            }

            $this->defaults[$ability->value] = $this->defaultFor($ability);
            $this->gate->define($ability->value, $this->defaults[$ability->value]);
        }
    }

    /** Whether the ability still answers with the package's default rather than a rule of the host. */
    public function answeredByDefault(Ability $ability): bool
    {
        $defined = $this->gate->abilities()[$ability->value] ?? null;

        return $defined !== null && $defined === ($this->defaults[$ability->value] ?? null);
    }

    private function defaultFor(Ability $ability): Closure
    {
        $parent = $ability->parent();

        if ($parent === null) {
            return static fn (?Authenticatable $account = null): bool => $account !== null;
        }

        $gate = $this->gate;

        return static fn (?Authenticatable $account = null, mixed ...$arguments): bool => $gate
            ->forUser($account)
            ->allows($parent->value, $arguments);
    }
}
