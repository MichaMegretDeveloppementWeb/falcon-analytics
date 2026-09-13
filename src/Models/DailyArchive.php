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
 */
final class DailyArchive extends Model
{
    public const TABLE = 'falcon_analytics_daily_archives';

    protected $table = self::TABLE;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    /** @var list<string> */
    protected $fillable = ['day', 'archived_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
