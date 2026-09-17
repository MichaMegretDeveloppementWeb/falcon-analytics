<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One cached Search Console row: a query's clicks, impressions and average
 * position for one day. Written only by the sync command (upsert on
 * date+query); read by the dashboard.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $query
 * @property int $clicks
 * @property int $impressions
 * @property float|null $position
 */
final class SearchQuery extends Model
{
    protected $table = 'falcon_analytics_search_queries';

    public $timestamps = false;

    /**
     * Stored as a plain Y-m-d so Eloquent writes and the sync's raw upserts
     * produce byte-identical values for the date+query unique key.
     */
    protected $dateFormat = 'Y-m-d';

    /** @var list<string> */
    protected $fillable = ['date', 'query', 'clicks', 'impressions', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'position' => 'float',
        ];
    }
}
