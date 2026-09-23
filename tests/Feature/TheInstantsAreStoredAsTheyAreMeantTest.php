<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the disk holds is what the application means.
 *
 * The package writes UTC in plain: the application's zone is UTC, so a Carbon
 * instant is handed over as a naive `Y-m-d H:i:s` string. A column the engine
 * converts reads that string as a *local* time and stores something else —
 * invisibly, since the reverse conversion runs on the way back.
 *
 * The round trip through the package therefore stays consistent, and its
 * screens say the truth. **Any other reader does not**: a backup restored
 * elsewhere, a replica, a reporting tool, an operations query.
 */
final class TheInstantsAreStoredAsTheyAreMeantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Summer and winter, because the offset is the server's at the instant of
     * the row: two hours in one, one hour in the other. A single sample would
     * prove nothing about the other half of the year.
     *
     * @return array<string, array{string}>
     */
    public static function instantsOnBothSidesOfSummerTime(): array
    {
        return [
            'un instant d\'été' => ['2026-07-15 12:00:00'],
            'un instant d\'hiver' => ['2026-01-15 12:00:00'],
        ];
    }

    #[DataProvider('instantsOnBothSidesOfSummerTime')]
    public function test_the_stored_instant_is_the_one_the_application_wrote(string $instant): void
    {
        $written = CarbonImmutable::parse($instant, 'UTC');
        $session = $this->sessionStartedAt($written);

        // Read under a named zone, so the assertion does not depend on the machine.
        DB::statement("SET time_zone = '+00:00'");

        $stored = DB::table(Session::TABLE)
            ->where('id', $session->id)
            ->selectRaw('UNIX_TIMESTAMP(started_at) AS epoque')
            ->value('epoque');

        $this->assertSame(
            $written->getTimestamp(),
            (int) $stored,
            'Le disque doit porter l\'instant que l\'application a écrit, pas celui que le fuseau du serveur en a fait.',
        );
    }

    #[DataProvider('instantsOnBothSidesOfSummerTime')]
    public function test_the_application_reads_back_exactly_what_it_wrote(string $instant): void
    {
        $written = CarbonImmutable::parse($instant, 'UTC');
        $session = $this->sessionStartedAt($written);

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);

        // A converted type passes this round trip too; the test above is the proof.
        $this->assertSame($written->toDateTimeString(), $fresh->started_at->toDateTimeString());
    }

    /**
     * The hour the local clock skips does not exist as a local time, and a
     * column the engine converts has no room for it.
     */
    public function test_an_instant_in_the_hour_the_local_clock_skips_is_stored_whole(): void
    {
        // Paris clocks jump from 02:00 to 03:00 that day, so 02:30 never shows on the wall.
        $written = CarbonImmutable::parse('2026-03-29 02:30:00', 'UTC');
        $session = $this->sessionStartedAt($written);

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(
            $written->toDateTimeString(),
            $fresh->started_at->toDateTimeString(),
            'Un instant que la pendule locale ne connaît pas doit se ranger comme les autres.',
        );
    }

    private function sessionStartedAt(CarbonImmutable $instant): Session
    {
        return Session::factory()
            ->for(Visitor::factory()->state(['first_seen_at' => $instant, 'last_seen_at' => $instant]))
            ->at($instant)
            ->create(['pageview_count' => 1]);
    }
}
