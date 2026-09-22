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
     * **A model has ONE `$dateFormat`, and this table has two kinds of column**
     * · a date that everything queries on, and a timestamp. Set to `Y-m-d` for
     * the key, it reaches the timestamp too: an archiving run at 03:30 is then
     * recorded as having happened at 00:00, without a word.
     *
     * So the format is left alone — the timestamp keeps its hour — and the key
     * says for itself what it is. A mutator is the only thing that reaches
     * the write: a format given to a cast serves `toArray()`, never the
     * statement.
     *
     * Written as a date rather than at midnight because a `date` column is
     * compared as a string, and `2026-01-05 00:00:00` finds nothing on an
     * engine that stores what it was given.
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
