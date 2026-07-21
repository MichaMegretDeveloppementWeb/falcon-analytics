<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function rawTaggedSessions(int $count): void
{
    $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
            'mkt_params' => '{"src":"meta_ete"}',
        ];
    }

    Session::query()->insert($rows);
}

it('caps the tagged session read at its ceiling and logs a warning when truncated', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    rawTaggedSessions(4);

    Log::shouldReceive('channel')->once()->andReturnSelf();
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'Marketing.tagged_sessions_truncated');

    expect((new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null))
        ->toHaveCount(3);
});

it('returns every tagged session without logging below the ceiling', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    rawTaggedSessions(3);

    Log::shouldReceive('channel')->never();
    Log::shouldReceive('warning')->never();

    expect((new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null))
        ->toHaveCount(3)
        ->and((new MarketingReadRepository)->taggedSessionRows(Period::ofDays(30), null))->toHaveCount(3);
});
