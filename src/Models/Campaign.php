<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $platform
 * @property array<int, array{param: string, value: string}>|null $match_conditions
 * @property bool $is_active
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, Ad> $ads
 */
final class Campaign extends Model
{
    protected $table = 'falcon_analytics_campaigns';

    /** @var list<string> */
    protected $fillable = ['name', 'platform', 'match_conditions', 'is_active'];

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'match_conditions' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
