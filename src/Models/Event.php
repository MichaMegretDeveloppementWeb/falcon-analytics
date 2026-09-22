<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Support\StoredUrl;
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
 * @property string|null $page
 * @property string|null $target_selector
 * @property string|null $target_text
 * @property array<string, mixed>|null $props
 * @property int|null $value
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property-read Session $session
 * @property-read Visitor $visitor
 */
final class Event extends Model
{
    /** Named because the archiving and the purge build joins by hand. */
    public const TABLE = 'falcon_analytics_events';

    protected $table = self::TABLE;

    public $timestamps = false;

    /**
     * Internal model: writes always go through the package repositories with
     * explicit attribute arrays (never raw request input), so mass assignment
     * is intentionally unguarded.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The page is derived from the address whenever a row is made through the
     * model and nobody said otherwise.
     *
     * The ingestion writes in bulk and sets it itself, by the same function ·
     * this is for every other writer, so that a row can never carry an address
     * and no page. One source for the reading, wherever the row comes from.
     */
    protected static function booted(): void
    {
        self::creating(function (Event $event): void {
            if ($event->page === null && $event->url !== null) {
                $event->page = StoredUrl::page($event->url);
            }
        });
    }

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
            'value' => 'integer',
            'session_id' => 'integer',
            'visitor_id' => 'integer',
            'subject_id' => 'integer',
        ];
    }
}
