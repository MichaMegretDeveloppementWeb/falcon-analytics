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
 * @property string $label_hash
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
    protected $fillable = ['day', 'kind', 'label_hash', 'label', 'route', 'subject_type', 'total'];

    /**
     * The key a label is indexed under.
     *
     * **Not a cryptographic need, a length one** · a page address runs to 2048
     * characters and an index holds far less, so the index carries this and the
     * column carries the address, readable.
     */
    public static function hash(string $label): string
    {
        return hash('sha256', $label);
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
