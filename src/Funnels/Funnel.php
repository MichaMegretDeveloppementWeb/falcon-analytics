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
     * Add a weighted step matched by a named event XOR a pageview route.
     */
    public function step(string $label, float $value, ?string $event = null, ?string $route = null): self
    {
        if (($event === null) === ($route === null)) {
            throw new InvalidArgumentException("Funnel step [{$label}] must match exactly one of event or route.");
        }

        $this->steps[] = new FunnelStep($label, $value, $event, $route);

        return $this;
    }

    /** @return list<FunnelStep> */
    public function steps(): array
    {
        return $this->steps;
    }
}
