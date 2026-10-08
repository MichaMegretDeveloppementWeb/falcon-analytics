<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a day's sessions amounted to, for one subject type and one group of a
 * breakdown · the day as a whole, a device, a locality or a source.
 *
 * @internal the host reads screens, never this · it is how the retention keeps
 *           its promise, and it is free to change shape
 *
 * @property int $id
 * @property CarbonImmutable $day
 * @property string $dimension
 * @property string $signature
 * @property string|null $subject_type
 * @property string|null $key_a
 * @property string|null $key_b
 * @property array<string, string>|null $mkt_params
 * @property int $sessions
 * @property int $pageviews
 * @property int $seconds
 * @property int $bounces
 */
final class DailySessionTotal extends Model
{
    public const TABLE = 'falcon_analytics_daily_sessions';

    public const DIMENSION_ALL = 'all';

    public const DIMENSION_DEVICE = 'device';

    public const DIMENSION_LOCALITY = 'locality';

    public const DIMENSION_SOURCE = 'source';

    protected $table = self::TABLE;

    public $timestamps = false;

    /**
     * What identifies a row inside its day, as one indexable value · hashed for
     * length, and encoded rather than joined for the reason `DailyCount`
     * gives.
     */
    public static function signature(string $dimension, ?string $subjectType, ?string $keyA, ?string $keyB, ?string $mktParams): string
    {
        return hash('sha256', json_encode([$dimension, $subjectType, $keyA, $keyB, $mktParams], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'mkt_params' => 'array',
            'sessions' => 'integer',
            'pageviews' => 'integer',
            'seconds' => 'integer',
            'bounces' => 'integer',
        ];
    }
}
