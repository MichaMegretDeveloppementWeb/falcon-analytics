<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\DailyEventTotal;
use Falcon\Analytics\Models\DailySessionTotal;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\DailyDetailArchiver;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The daily totals of sessions and named events say what the rows say · on a
 * day where both exist, family by family and subject by subject.
 */
final class TheTotalsAgreeWithTheSessionsTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-06-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    }

    /**
     * Everyone, the visitors no guard named, and each guard.
     *
     * @return array<string, array{string|null}>
     */
    public static function audiences(): array
    {
        return [
            'everyone' => [null],
            'no guard named them' => ['none'],
            'a client' => ['client'],
            'a lessor' => ['lessor'],
        ];
    }

    #[DataProvider('audiences')]
    public function test_the_engagement_figures_agree(?string $audience): void
    {
        $this->aDayOfEverything();
        $this->summarise();

        $read = $this->app->make(EngagementReadRepository::class)->headlineCounts($this->theDay(), $audience);
        $totals = $this->sessionTotals(DailySessionTotal::DIMENSION_ALL, $audience);
        $sessions = array_sum(array_column($totals, 'sessions'));

        $this->assertSame($read['sessions'], $sessions);
        $this->assertSame($read['pageviews'], array_sum(array_column($totals, 'pageviews')));
        $this->assertSame($read['bounces'], array_sum(array_column($totals, 'bounces')));
        $this->assertEqualsWithDelta($read['avgSeconds'], $sessions === 0 ? 0.0 : array_sum(array_column($totals, 'seconds')) / $sessions, 0.0001);
    }

    #[DataProvider('audiences')]
    public function test_the_devices_localities_and_sources_agree(?string $audience): void
    {
        $this->aDayOfEverything();
        $this->summarise();
        $overview = $this->app->make(OverviewReadRepository::class);

        $this->assertEquals($overview->sessionsByDevice($this->theDay(), $audience), $this->sessionsBy(DailySessionTotal::DIMENSION_DEVICE, $audience));

        $localities = [];
        foreach ($overview->topLocalities($this->theDay(), $audience, 100) as $row) {
            $localities[$row['country'].'|'.$row['city']] = $row['total'];
        }
        $this->assertEquals($localities, $this->sessionsBy(DailySessionTotal::DIMENSION_LOCALITY, $audience));

        $sources = [];
        foreach ($overview->topSources($this->theDay(), $audience, 100) as $row) {
            $sources[$row['label']] = $row['total'];
        }
        $this->assertEquals($sources, $this->sessionsBy(DailySessionTotal::DIMENSION_SOURCE, $audience));
    }

    #[DataProvider('audiences')]
    public function test_the_named_events_agree(?string $audience): void
    {
        $this->aDayOfEverything();
        $this->summarise();

        $read = [];
        foreach ($this->app->make(EventReadRepository::class)->eventBreakdown($this->theDay(), $audience, $this->app->make(EventRegistry::class)) as $row) {
            $read[$row['name']] = [$row['count'], $row['valueTotal']];
        }

        $totals = [];
        foreach ($this->eventTotals($audience) as $row) {
            $totals[$row->name][0] = ($totals[$row->name][0] ?? 0) + $row->total;
            $totals[$row->name][1] = ($totals[$row->name][1] ?? 0) + $row->value_sum;
        }

        $this->assertEquals($read, $totals);
    }

    /** What a campaign claims at reading time is kept with the source · the parameters the session arrived with. */
    public function test_the_parameters_a_session_arrived_with_are_kept(): void
    {
        $this->aDayOfEverything();
        $this->summarise();

        $meta = DailySessionTotal::query()
            ->where('dimension', DailySessionTotal::DIMENSION_SOURCE)
            ->where('key_a', 'meta')
            ->sole();

        $this->assertEquals(['utm_campaign' => 'ete', 'utm_source' => 'meta'], $meta->mkt_params);
        $this->assertSame(2, $meta->sessions);
    }

    /** The scores carried, and how many carried one · the declared score is the reading's to add. */
    public function test_the_scores_carried_are_kept_apart_from_those_missing(): void
    {
        $this->aDayOfEverything();
        $this->summarise();

        $request = DailyEventTotal::query()->where('name', 'devis.demande')->where('subject_type', 'client')->sole();

        $this->assertSame([2, 3, 1], [$request->total, $request->value_sum, $request->value_count]);
        $this->assertSame(0, DailyEventTotal::query()->where('name', '')->count(), 'An empty name names no event.');
    }

    /**
     * Sessions and named events were never erased · the whole history is
     * summarised on the first pass, days already summarised for their pages
     * included, and their pages are not summarised again.
     */
    public function test_the_whole_history_is_summarised_on_the_first_pass(): void
    {
        foreach (range(1, 14) as $day) {
            DailyArchive::query()->create(['day' => sprintf('2026-06-%02d', $day), 'archived_at' => '2026-06-15 03:00:00']);
        }
        $this->visit(null, '2026-06-03 10:00:00');
        $this->visit(null, '2026-06-10 10:00:00');
        DailyCount::query()->create(['day' => '2026-06-03', 'kind' => DailyCount::KIND_PAGE, 'signature' => str_repeat('a', 64), 'label' => '/', 'total' => 99]);

        $this->summarise();

        $this->assertSame(1, $this->sessionsOn('2026-06-03'));
        $this->assertSame(1, $this->sessionsOn('2026-06-10'));
        $this->assertSame(0, $this->sessionsOn('2026-06-04'));
        $this->assertSame(99, (int) DailyCount::query()->where('day', '2026-06-03')->sum('total'), 'The pages of a day already summarised were summarised again.');
        $this->assertSame(12, DailyArchive::query()->whereNotNull('detail_archived_at')->count(), 'From the oldest session to yesterday, without a gap.');
    }

    /** A day not yet closed waits · the grace after midnight included. */
    public function test_a_day_not_yet_closed_waits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 00:30:00'));
        $this->visit(null, '2026-06-14 23:30:00');
        $this->visit(null, '2026-06-13 10:00:00');

        $this->summarise();

        $this->assertSame(1, $this->sessionsOn('2026-06-13'));
        $this->assertFalse($this->app->make(DailyDetailArchiver::class)->isArchived(CarbonImmutable::parse('2026-06-14')));
        $this->assertSame(0, DailySessionTotal::query()->where('day', '2026-06-14')->count());
    }

    /** A day summarised again replaces its totals, and never adds to them. */
    public function test_a_day_summarised_again_replaces_its_totals(): void
    {
        $this->visit(null, self::DAY.' 10:00:00');
        $this->summarise();

        $this->visit(null, self::DAY.' 11:00:00');
        $this->app->make(ArchiveClosedDaysAction::class)->executeFrom(CarbonImmutable::parse(self::DAY));

        $this->assertSame(2, $this->sessionsOn(self::DAY));
    }

    /**
     * A day whose sessions are summarised before its pages is not taken for
     * a day whose pages are · the page reading still splits where its own
     * summaries end.
     */
    public function test_a_day_summarised_for_its_sessions_alone_is_not_one_for_its_pages(): void
    {
        $this->visit(null, '2026-06-05 10:00:00', pageviews: 0);
        $session = $this->visit(null, '2026-06-07 10:00:00');
        Event::factory()->for($session)->create(['occurred_at' => '2026-06-07 10:00:00', 'url' => 'https://site.test/', 'page' => '/']);

        $this->summarise();

        $this->assertTrue($this->app->make(DailyDetailArchiver::class)->isArchived(CarbonImmutable::parse('2026-06-05')));
        $this->assertFalse($this->app->make(DailyCountArchiver::class)->isArchived(CarbonImmutable::parse('2026-06-05')));
        $this->assertTrue($this->app->make(DailyCountArchiver::class)->isArchived(CarbonImmutable::parse('2026-06-07')));
        $this->assertSame('2026-06-14', DailyArchive::lastSummarisedDay()?->toDateString());
        $this->assertSame([], $this->app->make(DailyCountArchiver::class)->pendingDays());
    }

    /** The page reading splits where the page summaries end, whatever the sessions' have reached. */
    public function test_the_page_reading_splits_where_its_own_summaries_end(): void
    {
        foreach (range(1, 14) as $day) {
            DailyArchive::query()->create([
                'day' => sprintf('2026-06-%02d', $day),
                'archived_at' => $day <= 10 ? '2026-06-15 03:00:00' : null,
                'detail_archived_at' => '2026-06-15 03:00:00',
            ]);
        }

        $this->assertSame('2026-06-10', DailyArchive::lastSummarisedDay()?->toDateString());
        $this->assertSame('2026-06-14', DailyArchive::lastDetailSummarisedDay()?->toDateString());
        $this->assertSame(
            ['2026-06-11', '2026-06-12', '2026-06-13', '2026-06-14'],
            array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $this->app->make(DailyCountArchiver::class)->pendingDays()),
        );
    }

    /** A day of ten sessions and a day of a hundred are summarised in as many statements. */
    public function test_the_statements_do_not_grow_with_the_sessions(): void
    {
        $this->assertSame($this->statementsToSummarise(10, '2026-06-08'), $this->statementsToSummarise(100, '2026-06-09'));
    }

    private function statementsToSummarise(int $sessions, string $day): int
    {
        foreach (range(1, $sessions) as $rank) {
            $this->visit(null, $day.' 10:00:00', city: "Ville {$rank}");
        }

        $statements = 0;
        DB::listen(function () use (&$statements): void {
            $statements++;
        });

        DB::transaction(fn () => $this->app->make(DailyDetailArchiver::class)->archive(CarbonImmutable::parse($day)));

        DB::getEventDispatcher()?->forget('Illuminate\Database\Events\QueryExecuted');

        return $statements;
    }

    /**
     * A day with what each total has to agree about · three subjects and
     * nobody, a known visitor back without signing in, devices, localities
     * with and without a city, a source arrived by an ad, a bounce, durations,
     * scores carried and missing, an empty name, and a bot that counts nowhere.
     */
    private function aDayOfEverything(): void
    {
        $anonymous = $this->visit(null, self::DAY.' 09:00:00', device: 'desktop', city: 'Paris', source: 'google', pageviews: 3, seconds: 120);
        $bounce = $this->visit(null, self::DAY.' 09:30:00', device: 'mobile', city: 'Lyon', source: 'direct', pageviews: 1, seconds: 0);
        $client = Visitor::factory()->create(['subject_type' => 'client', 'subject_id' => 1]);
        $first = $this->visit(['type' => 'client', 'id' => 1], self::DAY.' 10:00:00', device: 'desktop', city: 'Paris', source: 'meta', pageviews: 2, seconds: 300, visitor: $client, parameters: ['utm_source' => 'meta', 'utm_campaign' => 'ete']);
        $second = $this->visit(['type' => 'client', 'id' => 1], self::DAY.' 15:00:00', device: 'mobile', country: 'CH', city: 'Genève', source: 'meta', pageviews: 1, seconds: 10, visitor: $client, parameters: ['utm_source' => 'meta', 'utm_campaign' => 'ete']);
        $lessor = $this->visit(['type' => 'lessor', 'id' => 2], self::DAY.' 16:00:00', device: 'tablet', country: null, city: null, source: null, pageviews: 0, seconds: 5);
        $signedOut = $this->visit(null, self::DAY.' 18:00:00', device: 'desktop', city: 'Paris', source: 'direct', pageviews: 2, seconds: 40, visitor: $client);
        $bot = $this->visit(null, self::DAY.' 17:00:00', device: 'desktop', city: 'Paris', source: 'google', pageviews: 4, seconds: 50, bot: true);

        $this->named($anonymous, 'devis.demande', 5);
        $this->named($first, 'devis.demande', null);
        $this->named($second, 'devis.demande', 3);
        $this->named($lessor, 'inscription', null);
        $this->named($signedOut, 'inscription', 4);
        $this->named($bounce, '', 2);
        $this->named($bot, 'devis.demande', 7);
        $this->visit(null, '2026-06-11 09:00:00');
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     * @param  array<string, string>|null  $parameters
     */
    private function visit(
        ?array $subject,
        string $at,
        string $device = 'desktop',
        ?string $country = 'FR',
        ?string $city = 'Paris',
        ?string $source = 'direct',
        int $pageviews = 1,
        int $seconds = 30,
        bool $bot = false,
        ?Visitor $visitor = null,
        ?array $parameters = null,
    ): Session {
        $start = CarbonImmutable::parse($at);
        $visitor ??= Visitor::factory()->create(['subject_type' => $subject['type'] ?? null, 'subject_id' => $subject['id'] ?? null]);

        return Session::factory()->for($visitor)->create([
            'started_at' => $start,
            'last_activity_at' => $start->addSeconds($seconds),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'device_type' => $device,
            'country' => $country,
            'city' => $city,
            'source' => $source,
            'mkt_params' => $parameters,
            'pageview_count' => $pageviews,
            'is_bot' => $bot,
        ]);
    }

    private function named(Session $session, string $name, ?int $value): void
    {
        Event::factory()->for($session)->custom($name)->create(['occurred_at' => $session->started_at, 'value' => $value]);
    }

    private function summarise(): void
    {
        $this->app->make(ArchiveClosedDaysAction::class)->execute();
    }

    private function theDay(): Period
    {
        $day = CarbonImmutable::parse(self::DAY);

        return new Period($day->startOfDay(), $day->endOfDay(), 1);
    }

    private function sessionsOn(string $day): int
    {
        return (int) DailySessionTotal::query()->where('day', $day)->where('dimension', DailySessionTotal::DIMENSION_ALL)->sum('sessions');
    }

    /**
     * @return list<DailySessionTotal>
     */
    private function sessionTotals(string $dimension, ?string $audience): array
    {
        return array_values($this->forAudience(DailySessionTotal::query()->where('day', self::DAY)->where('dimension', $dimension), $audience)->get()->all());
    }

    /**
     * The sessions of a breakdown, by its key · the way the readings key them.
     *
     * @return array<string, int>
     */
    private function sessionsBy(string $dimension, ?string $audience): array
    {
        $sessions = [];

        foreach ($this->sessionTotals($dimension, $audience) as $row) {
            if ($row->key_a === null) {
                continue;
            }

            $key = $dimension === DailySessionTotal::DIMENSION_LOCALITY ? $row->key_a.'|'.$row->key_b : $row->key_a;
            $sessions[$key] = ($sessions[$key] ?? 0) + $row->sessions;
        }

        return $sessions;
    }

    /**
     * @return list<DailyEventTotal>
     */
    private function eventTotals(?string $audience): array
    {
        return array_values($this->forAudience(DailyEventTotal::query()->where('day', self::DAY), $audience)->get()->all());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function forAudience(Builder $query, ?string $audience): Builder
    {
        return match ($audience) {
            null => $query,
            'none' => $query->whereNull('subject_type'),
            default => $query->where('subject_type', $audience),
        };
    }
}
