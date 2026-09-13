<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A day that has been summarised.
 *
 * The purge reads this and refuses to erase a day it does not find, which is
 * what makes a dead scheduler harmless: no archiving, no erasing.
 *
 * @internal how the retention keeps its promise · not something a host reads
 *
 * @property int $id
 * @property CarbonImmutable $day
 * @property CarbonImmutable $archived_at
 * @property CarbonImmutable|null $pruned_at
 */
final class DailyArchive extends Model
{
    public const TABLE = 'falcon_analytics_daily_archives';

    protected $table = self::TABLE;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    /** @var list<string> */
    protected $fillable = ['day', 'archived_at', 'pruned_at'];

    /**
     * The last day whose detail has been erased, or null while none has.
     *
     * **The reading splits here** · up to and including this day the figures
     * come from the summaries, after it from the rows themselves. Asked of the
     * table rather than worked out from the retention, because the two part
     * company as soon as a scheduler stops or a retention is shortened.
     */
    public static function lastPrunedDay(): ?CarbonImmutable
    {
        $day = self::query()->whereNotNull('pruned_at')->max('day');

        return is_string($day) && $day !== '' ? CarbonImmutable::parse($day)->startOfDay() : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'archived_at' => 'immutable_datetime',
            'pruned_at' => 'immutable_datetime',
        ];
    }
}
