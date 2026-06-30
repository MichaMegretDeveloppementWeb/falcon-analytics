<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $session_count
 * @property-read Collection<int, Session> $sessions
 * @property-read Collection<int, Event> $events
 */
final class Visitor extends Model
{
    protected $table = 'falcon_analytics_visitors';

    public $timestamps = false;

    /**
     * Internal model: writes always go through the package repositories with
     * explicit attribute arrays (never raw request input), so mass assignment
     * is intentionally unguarded.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /** @return HasMany<Session, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
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
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'subject_id' => 'integer',
            'session_count' => 'integer',
        ];
    }
}
