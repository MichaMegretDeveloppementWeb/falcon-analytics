<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\SubjectName;
use Falcon\Analytics\DTOs\Dashboard\Visitor\VisitorRow;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Prepares the lines of the visitor directory from a page of visitors already
 * read · the names come in one query per guard.
 *
 * @internal
 */
final readonly class VisitorRowBuilder
{
    public function __construct(private SubjectResolver $subjects) {}

    /**
     * @param  LengthAwarePaginator<int, Visitor>  $visitors
     * @return LengthAwarePaginator<int, VisitorRow>
     */
    public function build(LengthAwarePaginator $visitors): LengthAwarePaginator
    {
        $subjects = [];
        foreach ($visitors->items() as $visitor) {
            if (filled($visitor->subject_type) && $visitor->subject_id !== null) {
                $subjects[] = [$visitor->subject_type, $visitor->subject_id];
            }
        }

        $names = $this->subjects->shownNames($subjects);

        return $visitors->through(fn (Visitor $visitor): VisitorRow => self::row($visitor, $names[$visitor->subject_type.':'.$visitor->subject_id] ?? null));
    }

    private static function row(Visitor $visitor, ?SubjectName $subject): VisitorRow
    {
        return new VisitorRow(
            id: $visitor->id,
            name: $subject->name ?? __('Visiteur #:id', ['id' => $visitor->id]),
            kind: $subject?->label,
            uuid: $visitor->uuid,
            sessionCount: $visitor->session_count,
            firstSeenAt: $visitor->first_seen_at,
            lastSeenAt: $visitor->last_seen_at,
            country: self::text($visitor->getAttribute('last_country')),
            city: self::text($visitor->getAttribute('last_city')),
            source: self::text($visitor->getAttribute('acquisition_source')),
        );
    }

    /** A value read by a subquery, when it holds text. */
    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
