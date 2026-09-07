<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class VisitorWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VisitorWriteRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new VisitorWriteRepository;
    }

    public function test_it_resolves_a_visitor_once_and_refreshes_it_on_later_calls(): void
    {
        $created = $this->repository->resolve(
            'uuid-r',
            CarbonImmutable::parse('2026-07-01 10:00:00'),
            ['type' => 'client', 'id' => 3],
        );

        $this->assertTrue($created->wasRecentlyCreated);
        $this->assertSame('uuid-r', $created->uuid);
        $this->assertSame(0, $created->session_count);
        $this->assertSame('client', $created->subject_type);
        $this->assertSame(3, $created->subject_id);

        $again = $this->repository->resolve('uuid-r', CarbonImmutable::parse('2026-07-01 11:00:00'), null);

        $this->assertSame($created->id, $again->id);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame('2026-07-01 11:00:00', $again->fresh()->last_seen_at->toDateTimeString());
    }

    public function test_it_resolves_an_anonymous_visitor_without_a_subject(): void
    {
        $visitor = $this->repository->resolve('uuid-anon', CarbonImmutable::now(), null);

        $this->assertNull($visitor->subject_type);
        $this->assertNull($visitor->subject_id);
    }

    public function test_it_refreshes_last_seen_and_stitches_the_subject_once(): void
    {
        $visitor = $this->repository->resolve('uuid-3', CarbonImmutable::parse('2026-06-01 00:00:00'), null);

        $this->repository->markSeen($visitor, CarbonImmutable::parse('2026-06-30 12:00:00'), ['type' => 'lessor', 'id' => 9]);

        $fresh = $visitor->fresh();

        $this->assertSame('2026-06-30 12:00:00', $fresh->last_seen_at->toDateTimeString());
        $this->assertSame('lessor', $fresh->subject_type);
        $this->assertSame(9, $fresh->subject_id);
    }

    public function test_it_never_overwrites_an_already_stitched_subject(): void
    {
        $visitor = $this->repository->resolve('uuid-4', CarbonImmutable::now(), ['type' => 'client', 'id' => 1]);

        $this->repository->markSeen($visitor, CarbonImmutable::now(), ['type' => 'lessor', 'id' => 2]);

        $fresh = $visitor->fresh();

        $this->assertSame('client', $fresh->subject_type);
        $this->assertSame(1, $fresh->subject_id);
    }

    public function test_it_increments_the_session_count(): void
    {
        $visitor = $this->repository->resolve('uuid-5', CarbonImmutable::now(), null);

        $this->repository->incrementSessionCount($visitor);
        $this->repository->incrementSessionCount($visitor);

        $this->assertSame(2, $visitor->fresh()->session_count);
    }
}
