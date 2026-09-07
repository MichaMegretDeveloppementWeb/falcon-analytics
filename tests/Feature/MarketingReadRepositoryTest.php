<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MarketingReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** Des sessions taguees, ecrites en lot pour sauter les casts du modele. */
    private function rawTaggedSessions(int $count): void
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

    public function test_it_caps_the_tagged_session_read_at_its_ceiling_and_logs_a_warning_when_truncated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $this->rawTaggedSessions(4);

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'Marketing.tagged_sessions_truncated');

        $this->assertCount(
            3,
            (new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null),
        );
    }

    public function test_it_returns_every_tagged_session_without_logging_below_the_ceiling(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $this->rawTaggedSessions(3);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('warning')->never();

        $this->assertCount(
            3,
            (new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null),
        );

        $this->assertCount(
            3,
            (new MarketingReadRepository)->taggedSessionRows(Period::ofDays(30), null),
        );
    }
}
