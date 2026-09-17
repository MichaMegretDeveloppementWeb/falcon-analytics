<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use InvalidArgumentException;

/**
 * A funnel is an ordered list of weighted steps, declared in code. The same
 * event may belong to several funnels with a different value in each.
 */
final class Funnel
{
    /** @var list<FunnelStep> */
    private array $steps = [];

    public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    /**
     * Declare a funnel and register it with the loading registry. Chain
     * ->step() to add its steps.
     */
    public static function define(string $key, string $label): self
    {
        $funnel = new self($key, $label);
        FunnelRegistry::current()?->register($funnel);

        return $funnel;
    }

    /**
     * Add a weighted step, matched by exactly one of: a named event, a pageview
     * route, or a set of parallel branches.
     *
     * Branches describe alternative ways of reaching the same milestone -- a
     * form opened from either of two pages, a signup completed through either
     * of two flows. They sit at the same depth, so a visitor advances once
     * whichever one they take, and the report tells which.
     *
     * @param  list<FunnelBranch>|null  $anyOf
     */
    public function step(string $label, float $value, ?string $event = null, ?string $route = null, ?array $anyOf = null): self
    {
        $declared = count(array_filter([$event, $route, $anyOf], static fn (mixed $value): bool => $value !== null));

        if ($declared !== 1) {
            throw new InvalidArgumentException("Funnel step [{$label}] must match exactly one of event, route or anyOf.");
        }

        if ($anyOf !== null && count($anyOf) < 2) {
            throw new InvalidArgumentException("Funnel step [{$label}] declares anyOf with fewer than two branches.");
        }

        $this->steps[] = new FunnelStep($label, $value, $event, $route, $anyOf ?? []);

        return $this;
    }

    /** @return list<FunnelStep> */
    public function steps(): array
    {
        return $this->steps;
    }
}
