<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdObjective>
 */
final class AdObjectiveFactory extends Factory
{
    protected $model = AdObjective::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'type' => ObjectiveType::Event,
            'reference' => 'objective.'.Str::lower(Str::random(6)),
        ];
    }

    /** An objective met when the named event happens. */
    public function event(string $name): self
    {
        return $this->state(fn (): array => ['type' => ObjectiveType::Event, 'reference' => $name]);
    }

    /** An objective met when the visit completes the funnel. */
    public function funnel(string $key): self
    {
        return $this->state(fn (): array => ['type' => ObjectiveType::Funnel, 'reference' => $key]);
    }
}
