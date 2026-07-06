<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $key
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, AdObjective> $objectives
 */
final class Ad extends Model
{
    protected $table = 'falcon_analytics_ads';

    /** @var list<string> */
    protected $fillable = ['campaign_id', 'key', 'name', 'is_active'];

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return HasMany<AdObjective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(AdObjective::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
