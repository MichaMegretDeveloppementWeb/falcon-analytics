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
use UnexpectedValueException;

final class MarketingReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** Sessions of one visitor, all arrived through the campaign link. */
    private function rawTaggedSessions(int $count): void
    {
        Session::factory()->count($count)->for(Visitor::factory())->create(['mkt_params' => ['src' => 'meta_ete']]);
    }

    public function test_it_caps_the_tagged_session_read_at_its_ceiling_and_says_it_was_truncated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $this->rawTaggedSessions(4);

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'Marketing.tagged_sessions_truncated');

        $read = (new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null);

        $this->assertCount(3, $read->rows);
        $this->assertTrue($read->truncated);
        $this->assertSame(3, $read->ceiling);
    }

    /** Beyond the ceiling the latest sessions are kept, the later id first between two of the same instant. */
    public function test_the_ceiling_keeps_the_latest_sessions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $visitor = Visitor::factory()->create();
        $tagged = fn (string $at): Session => Session::factory()->for($visitor)->at(CarbonImmutable::parse($at))->create(['mkt_params' => ['src' => 'meta_ete']]);

        $latest = $tagged('2026-06-14 10:00:00');
        $tagged('2026-06-01 10:00:00');
        $tied = $tagged('2026-06-10 10:00:00');
        $tiedLater = $tagged('2026-06-10 10:00:00');

        $read = (new MarketingReadRepository(maxTaggedSessions: 2))->taggedSessionRows(Period::ofDays(30), null);

        $this->assertSame([$latest->id, $tiedLater->id], $read->rows->pluck('id')->all());
        $this->assertNotContains($tied->id, $read->rows->pluck('id')->all());
    }

    public function test_it_returns_every_tagged_session_without_logging_below_the_ceiling(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $this->rawTaggedSessions(3);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('warning')->never();

        $atTheCeiling = (new MarketingReadRepository(maxTaggedSessions: 3))->taggedSessionRows(Period::ofDays(30), null);

        $this->assertCount(3, $atTheCeiling->rows);
        $this->assertFalse($atTheCeiling->truncated);

        $this->assertCount(3, app(MarketingReadRepository::class)->taggedSessionRows(Period::ofDays(30), null)->rows);
    }

    public function test_the_ceiling_is_the_hosts_to_set(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $this->rawTaggedSessions(3);
        config(['analytics.marketing.max_sessions' => 2]);

        $read = app(MarketingReadRepository::class)->taggedSessionRows(Period::ofDays(30), null);

        $this->assertCount(2, $read->rows);
        $this->assertTrue($read->truncated);
    }

    /** A ceiling that means nothing is refused, not replaced by one nobody chose. */
    public function test_a_ceiling_that_is_not_a_whole_number_of_sessions_is_refused(): void
    {
        config(['analytics.marketing.max_sessions' => 0]);

        $this->expectException(UnexpectedValueException::class);

        app(MarketingReadRepository::class)->taggedSessionRows(Period::ofDays(30), null);
    }
}
