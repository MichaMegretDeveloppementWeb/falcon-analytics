<?php

declare(strict_types=1);

namespace Falcon\Analytics\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** @var list<string> */
    protected $fillable = ['day', 'kind', 'signature', 'label', 'route', 'subject_type', 'total'];

    /**
     * The key, always written as a plain date · the same mutator as its
     * register, and for the same reason. See `DailyArchive::day()`.
     *
     * The archiving writes raw statements and bypasses it; it keeps any model
     * write on the key everything else queries on.
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
     * What identifies a row inside its day, as one indexable value.
     *
     * Hashed for length, not secrecy: an index over the four columns, a page
     * address among them, is longer than some engines accept, while the hash
     * fits any of them and the columns stay readable.
     *
     * Not joined by a separator: a label comes from `textContent`, which keeps
     * line feeds, so it can carry any separator, and two rows folding onto one
     * signature would have the unique index reject a row that is no duplicate.
     * `JSON_THROW_ON_ERROR` because a signature that silently became `false`
     * would collapse every row of a day onto one.
     */
    public static function signature(string $kind, string $label, ?string $route, ?string $subjectType): string
    {
        return hash('sha256', json_encode([$kind, $label, $route, $subjectType], JSON_THROW_ON_ERROR));
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
