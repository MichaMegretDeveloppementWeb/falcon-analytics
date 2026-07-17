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
     * batch. A uuid folded into another profile resolves to its canonical, which
     * gets the last-seen refresh and subject stitch instead.
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

        if ($visitor->wasRecentlyCreated) {
            return $visitor;
        }

        if ($visitor->merged_into_id !== null) {
            $visitor = $this->canonicalOf($visitor);
        }

        $this->markSeen($visitor, $seenAt, $subject);

        return $visitor;
    }

    /**
     * The profile an alias points to. Aliases always point at a root (never at
     * another alias), so one hop resolves; a dangling pointer falls back to the
     * alias itself rather than failing the ingestion.
     */
    public function canonicalOf(Visitor $visitor): Visitor
    {
        return Visitor::query()->find($visitor->merged_into_id) ?? $visitor;
    }

    /**
     * The subject's canonical profile (oldest non-merged row stitched to them),
     * optionally excluding one visitor id.
     */
    public function canonicalFor(string $subjectType, int $subjectId, ?int $excludeId = null): ?Visitor
    {
        return Visitor::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('merged_into_id')
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->orderBy('first_seen_at')
            ->orderBy('id')
            ->first();
    }
}
