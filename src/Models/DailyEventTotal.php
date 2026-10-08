<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a day's named events amounted to, for one name and one subject type of
 * the visitors who sent them.
 *
 * @internal the host reads screens, never this · it is how the retention keeps
 *           its promise, and it is free to change shape
 *
 * @property int $id
 * @property CarbonImmutable $day
 * @property string $signature
 * @property string|null $subject_type
 * @property string $name
 * @property int $total
 * @property int $value_sum
 * @property int $value_count
 */
final class DailyEventTotal extends Model
{
    public const TABLE = 'falcon_analytics_daily_events';

    protected $table = self::TABLE;

    public $timestamps = false;

    /**
     * What identifies a row inside its day, as one indexable value · hashed for
     * length, and encoded rather than joined for the reason `DailyCount`
     * gives.
     */
    public static function signature(?string $subjectType, string $name): string
    {
        return hash('sha256', json_encode([$subjectType, $name], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'total' => 'integer',
            'value_sum' => 'integer',
            'value_count' => 'integer',
        ];
    }
}
