<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 */
final class DailyArchive extends Model
{
    public const TABLE = 'falcon_analytics_daily_archives';

    protected $table = self::TABLE;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['day', 'archived_at'];

    /**
     * The key, always written as a plain date.
     *
     * Not `$dateFormat`: a model has only one, and `Y-m-d` would also strip the
     * hour from `archived_at`. Not a cast format either: it serves `toArray()`,
     * never the statement. Only a mutator reaches the write.
     *
     * A plain date and not midnight: a `date` column compares as a string, and a
     * value ending in `00:00:00` finds nothing on an engine that stores what it
     * was given.
     *
     * @return Attribute<CarbonImmutable, string>
     */
    protected function day(): Attribute
    {
        return Attribute::make(
            set: fn (CarbonInterface|string $value): string => $value instanceof CarbonInterface
                ? $value->toDateString()
                : CarbonImmutable::parse($value)->toDateString(),
        );
    }

    /**
     * The last day summarised, or null while none has been.
     *
     * **The reading splits here** · up to and including this day the figures
     * come from the summaries, after it from the rows themselves. Every day up
     * to it holds its summary, since the archiving goes forward from the
     * oldest row without leaving a gap.
     */
    public static function lastSummarisedDay(): ?CarbonImmutable
    {
        $day = self::query()->max('day');

        return is_string($day) && $day !== '' ? CarbonImmutable::parse($day)->startOfDay() : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
