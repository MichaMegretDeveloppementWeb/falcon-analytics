<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;

final readonly class VisitorWriteRepository
{
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

    /**
     * Race-safe find-or-create by uuid: firstOrCreate re-queries on a concurrent
     * unique-key violation, so two beacons with the same fresh uuid can't lose a
     * batch. Existing visitors get their last-seen refreshed and subject stitched.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    public function resolve(string $uuid, CarbonImmutable $seenAt, ?array $subject): Visitor
    {
        $visitor = Visitor::firstOrCreate(
            ['uuid' => $uuid],
            [
                'first_seen_at' => $seenAt,
                'last_seen_at' => $seenAt,
                'session_count' => 0,
                'subject_type' => $subject['type'] ?? null,
                'subject_id' => $subject['id'] ?? null,
            ],
        );

        if (! $visitor->wasRecentlyCreated) {
            $this->markSeen($visitor, $seenAt, $subject);
        }

        return $visitor;
    }
}
