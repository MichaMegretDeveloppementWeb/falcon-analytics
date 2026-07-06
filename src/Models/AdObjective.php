<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\ObjectiveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ad_id
 * @property ObjectiveType $type
 * @property string $reference
 * @property string|null $value
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Ad $ad
 */
final class AdObjective extends Model
{
    protected $table = 'falcon_analytics_ad_objectives';

    /** @var list<string> */
    protected $fillable = ['ad_id', 'type', 'reference', 'value'];

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ad_id' => 'integer',
            'type' => ObjectiveType::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
