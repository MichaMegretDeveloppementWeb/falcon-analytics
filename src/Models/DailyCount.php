<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a day's anonymous page views and clicks amounted to.
 *
 * Written by the archiving, read by the two overview blocks that count those
 * rows once the rows themselves are gone.
 *
 * @internal the host reads screens, never this · it is how the retention keeps
 *           its promise, and it is free to change shape
 *
 * @property int $id
 * @property CarbonImmutable $day
 * @property string $kind
 * @property string $signature
 * @property string $label
 * @property string|null $route
 * @property string|null $subject_type
 * @property int $total
 */
final class DailyCount extends Model
{
    public const TABLE = 'falcon_analytics_daily_counts';

    public const KIND_PAGE = 'page';

    public const KIND_CLICK = 'click';

    protected $table = self::TABLE;

    public $timestamps = false;

    /** Plain Y-m-d, so Eloquent and the archiving's raw writes agree on the key. */
    protected $dateFormat = 'Y-m-d';

    /** @var list<string> */
    protected $fillable = ['day', 'kind', 'signature', 'label', 'route', 'subject_type', 'total'];

    /**
     * What identifies a row inside its day, as one indexable value.
     *
     * **Not a cryptographic need, a length one** · a page address runs to 2048
     * characters, and an index over the four real columns comes to some 1 200
     * bytes — which MyISAM refuses. This is 64, the columns stay readable, and
     * every engine takes it.
     *
     * The separator is a line feed, which none of the four can hold · one they
     * could carry would let two different rows fold onto one signature, and the
     * unique index would then reject a row that was never a duplicate.
     */
    public static function signature(string $kind, string $label, ?string $route, ?string $subjectType): string
    {
        return hash('sha256', implode("\n", [$kind, $label, $route ?? '', $subjectType ?? '']));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'total' => 'integer',
        ];
    }
}
