<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;

final readonly class VisitorWriteRepository
{
    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    public function create(string $uuid, CarbonImmutable $seenAt, ?array $subject): Visitor
    {
        return Visitor::create([
            'uuid' => $uuid,
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'session_count' => 0,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);
    }

    /**
     * Refresh the last-seen timestamp and stitch the subject the first time the
     * visitor becomes identified (never overwrites an already-stitched subject).
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    public function markSeen(Visitor $visitor, CarbonImmutable $seenAt, ?array $subject): void
    {
        $attributes = ['last_seen_at' => $seenAt];

        if ($subject !== null && $visitor->subject_id === null) {
            $attributes['subject_type'] = $subject['type'];
            $attributes['subject_id'] = $subject['id'];
        }

        $visitor->update($attributes);
    }

    public function incrementSessionCount(Visitor $visitor): void
    {
        $visitor->increment('session_count');
    }
}
