<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new VisitorWriteRepository;
});

it('resolves a visitor once and refreshes it on later calls', function () {
    $created = $this->repo->resolve('uuid-r', CarbonImmutable::parse('2026-07-01 10:00:00'), ['type' => 'client', 'id' => 3]);

    expect($created->wasRecentlyCreated)->toBeTrue()
        ->and($created->uuid)->toBe('uuid-r')
        ->and($created->session_count)->toBe(0)
        ->and($created->subject_type)->toBe('client')
        ->and($created->subject_id)->toBe(3);

    $again = $this->repo->resolve('uuid-r', CarbonImmutable::parse('2026-07-01 11:00:00'), null);

    expect($again->id)->toBe($created->id)
        ->and($again->wasRecentlyCreated)->toBeFalse()
        ->and($again->fresh()->last_seen_at->toDateTimeString())->toBe('2026-07-01 11:00:00');
});

it('resolves an anonymous visitor without a subject', function () {
    $visitor = $this->repo->resolve('uuid-anon', CarbonImmutable::now(), null);

    expect($visitor->subject_type)->toBeNull()
        ->and($visitor->subject_id)->toBeNull();
});

it('refreshes last seen and stitches the subject once', function () {
    $visitor = $this->repo->resolve('uuid-3', CarbonImmutable::parse('2026-06-01 00:00:00'), null);
    $later = CarbonImmutable::parse('2026-06-30 12:00:00');

    $this->repo->markSeen($visitor, $later, ['type' => 'lessor', 'id' => 9]);

    $fresh = $visitor->fresh();
    expect($fresh->last_seen_at->toDateTimeString())->toBe('2026-06-30 12:00:00')
        ->and($fresh->subject_type)->toBe('lessor')
        ->and($fresh->subject_id)->toBe(9);
});

it('never overwrites an already stitched subject', function () {
    $visitor = $this->repo->resolve('uuid-4', CarbonImmutable::now(), ['type' => 'client', 'id' => 1]);

    $this->repo->markSeen($visitor, CarbonImmutable::now(), ['type' => 'lessor', 'id' => 2]);

    $fresh = $visitor->fresh();
    expect($fresh->subject_type)->toBe('client')
        ->and($fresh->subject_id)->toBe(1);
});

it('increments the session count', function () {
    $visitor = $this->repo->resolve('uuid-5', CarbonImmutable::now(), null);

    $this->repo->incrementSessionCount($visitor);
    $this->repo->incrementSessionCount($visitor);

    expect($visitor->fresh()->session_count)->toBe(2);
});
