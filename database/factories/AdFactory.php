<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Factories;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ad>
 */
final class AdFactory extends Factory
{
    protected $model = Ad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'name' => 'Pub '.Str::lower(Str::random(6)),
            'match_conditions' => null,
            'is_active' => true,
        ];
    }

    /** An ad recognised by one parameter of its links. */
    public function matching(string $param, string $value): self
    {
        return $this->state(fn (): array => ['match_conditions' => [['param' => $param, 'value' => $value]]]);
    }
}
