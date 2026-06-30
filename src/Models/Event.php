<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property int $visitor_id
 * @property CarbonImmutable $occurred_at
 * @property EventType $type
 * @property string|null $name
 * @property string|null $route
 * @property string|null $url
 * @property string|null $target_selector
 * @property string|null $target_text
 * @property array<string, mixed>|null $props
 * @property float|null $value
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property-read Session $session
 * @property-read Visitor $visitor
 */
final class Event extends Model
{
    protected $table = 'falcon_analytics_events';

    public $timestamps = false;

    /**
     * Internal model: writes always go through the package repositories with
     * explicit attribute arrays (never raw request input), so mass assignment
     * is intentionally unguarded.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /** @return BelongsTo<Session, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'type' => EventType::class,
            'props' => 'array',
            'value' => 'float',
            'session_id' => 'integer',
            'visitor_id' => 'integer',
            'subject_id' => 'integer',
        ];
    }
}
