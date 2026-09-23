<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
final class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Campagne '.Str::lower(Str::random(6)),
            'platform' => null,
            'match_conditions' => null,
            'is_active' => true,
        ];
    }

    /** A campaign recognised by one parameter of its links. */
    public function matching(string $param, string $value): self
    {
        return $this->state(fn (): array => ['match_conditions' => [['param' => $param, 'value' => $value]]]);
    }
}
