<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $visitor_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $ip
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $device_type
 * @property string|null $device_brand
 * @property string|null $device_model
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $os
 * @property string|null $os_version
 * @property bool $is_bot
 * @property string|null $referrer
 * @property string|null $source
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_content
 * @property string|null $utm_term
 * @property string|null $landing_route
 * @property string|null $landing_url
 * @property string|null $last_pageview_url
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $pageview_count
 * @property int $event_count
 * @property-read Visitor $visitor
 * @property-read Collection<int, Event> $events
 */
final class Session extends Model
{
    protected $table = 'falcon_analytics_sessions';

    public $timestamps = false;

    /**
     * Internal model: writes always go through the package repositories with
     * explicit attribute arrays (never raw request input), so mass assignment
     * is intentionally unguarded.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_bot' => 'boolean',
            'visitor_id' => 'integer',
            'subject_id' => 'integer',
            'pageview_count' => 'integer',
            'event_count' => 'integer',
        ];
    }
}
