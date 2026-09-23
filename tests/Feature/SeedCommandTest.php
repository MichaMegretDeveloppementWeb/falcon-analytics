<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SeedCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SMALL = ['--visits' => 12, '--days' => 5];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        config([
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
        ]);
        $this->app->forgetInstance(EventRegistry::class);
        $this->app->forgetInstance(FunnelRegistry::class);
    }

    public function test_it_refuses_outside_development(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('analytics:seed', self::SMALL)
            ->expectsOutputToContain('L’environnement est « production »')
            ->assertFailed();

        $this->assertSame(0, Visitor::query()->count());
        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_the_refusal_lifts_when_it_is_asked_to(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('analytics:seed', [...self::SMALL, '--force' => true])->assertSuccessful();

        $this->assertSame(12, Session::query()->count());
    }

    public function test_a_second_pass_adds_rather_than_finding_its_own_rows(): void
    {
        $this->artisan('analytics:seed', self::SMALL)->assertSuccessful();
        $campaigns = Campaign::query()->count();

        $this->artisan('analytics:seed', self::SMALL)->assertSuccessful();

        $this->assertSame(24, Session::query()->count(), 'Each pass adds its visits.');
        $this->assertSame($campaigns, Campaign::query()->count(), 'The demonstration campaigns are laid once.');
    }

    public function test_the_options_are_honoured(): void
    {
        $this->artisan('analytics:seed', ['--visits' => 7, '--days' => 2])->assertSuccessful();

        $this->assertSame(7, Session::query()->count());
        $this->assertSame(0, Session::query()->where('started_at', '<', now()->subDays(2)->startOfDay())->count());
    }

    public function test_a_negative_count_is_refused_before_anything_is_written(): void
    {
        $this->artisan('analytics:seed', ['--visits' => -1])
            ->expectsOutputToContain('--visits attend un nombre positif')
            ->assertFailed();

        $this->assertSame(0, Visitor::query()->count());
        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_no_visit_lies_in_the_future(): void
    {
        $this->artisan('analytics:seed', self::SMALL)->assertSuccessful();

        $this->assertSame(0, Session::query()->where('started_at', '>', now())->count());
        $this->assertSame(0, Event::query()->where('occurred_at', '>', now())->count());
    }

    public function test_the_screens_have_something_to_show(): void
    {
        $this->artisan('analytics:seed', ['--visits' => 80, '--days' => 10])->assertSuccessful();

        $this->assertTrue(Event::query()->where('type', EventType::Pageview)->exists(), 'pages seen');
        $this->assertTrue(Event::query()->where('type', EventType::Click)->exists(), 'clicks');
        $this->assertTrue(Event::query()->where('name', 'sample.action')->exists(), 'a declared event');
        $this->assertTrue(Session::query()->whereNotNull('country')->exists(), 'a place on the map');
        $this->assertTrue(Session::query()->where('started_at', '>=', now()->subMinutes(30))->exists(), 'something live');
        $this->assertGreaterThan(1, Session::query()->distinct()->count('source'), 'more than one channel');
        $this->assertGreaterThan(0, Visitor::query()->where('session_count', '>', 1)->count(), 'visitors who come back');

        $conversions = app(MarketingReportBuilder::class)->conversions(Period::ofDays(30), null, app(FunnelRegistry::class));

        $this->assertNotSame([], $conversions['campaigns'], 'a campaign credited with a conversion');
    }

    /** A visitor is known by their browser's cookie · coming back, they bring that browser, from the same town. */
    public function test_a_visitor_who_comes_back_keeps_their_browser_and_their_town(): void
    {
        $this->artisan('analytics:seed', ['--visits' => 80, '--days' => 10])->assertSuccessful();

        $drifting = Session::query()
            ->select('visitor_id')
            ->groupBy('visitor_id')
            ->havingRaw('COUNT(DISTINCT device_type) > 1 OR COUNT(DISTINCT browser) > 1 OR COUNT(DISTINCT city) > 1')
            ->get();

        $this->assertGreaterThan(0, Visitor::query()->where('session_count', '>', 1)->count(), 'visitors who come back');
        $this->assertSame([], $drifting->pluck('visitor_id')->all());
    }

    public function test_a_click_lands_on_the_page_the_visitor_is_on(): void
    {
        $this->artisan('analytics:seed', ['--visits' => 80, '--days' => 10])->assertSuccessful();

        $onScreen = [];
        $elsewhere = 0;

        foreach (Event::query()->orderBy('session_id')->orderBy('occurred_at')->orderBy('id')->get(['session_id', 'type', 'route']) as $event) {
            if ($event->type === EventType::Pageview) {
                $onScreen[$event->session_id] = $event->route;
            } elseif ($event->type === EventType::Click && $event->route !== ($onScreen[$event->session_id] ?? null)) {
                $elsewhere++;
            }
        }

        $this->assertTrue(Event::query()->where('type', EventType::Click)->exists(), 'clicks');
        $this->assertSame(0, $elsewhere);
    }

    public function test_it_refuses_more_days_than_the_retention_keeps(): void
    {
        config(['analytics.retention_days' => 3]);

        $this->artisan('analytics:seed', self::SMALL)
            ->expectsOutputToContain('--days dépasse la conservation')
            ->assertFailed();

        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_each_closed_day_is_summarised_as_its_detail_says(): void
    {
        $this->artisan('analytics:seed', ['--visits' => 40, '--days' => 4])->assertSuccessful();

        $this->assertEachClosedDaySaysWhatItsDetailSays(4);
    }

    /** Starts from a summary registry that already holds the closed days. */
    public function test_days_already_summarised_take_the_visits_laid_in_them(): void
    {
        Event::factory()->create(['url' => 'https://exemple.test/', 'occurred_at' => CarbonImmutable::now()->subDay()]);
        app(ArchiveClosedDaysAction::class)->execute();

        $this->artisan('analytics:seed', ['--visits' => 40, '--days' => 4])->assertSuccessful();

        $this->assertEachClosedDaySaysWhatItsDetailSays(4);
    }

    private function assertEachClosedDaySaysWhatItsDetailSays(int $days): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        for ($day = $today->subDays($days); $day->lessThan($today); $day = $day->addDay()) {
            $this->assertTrue(DailyArchive::query()->where('day', $day->toDateString())->exists(), "{$day->toDateString()} is summarised");

            $summarised = (int) DailyCount::query()
                ->where('day', $day->toDateString())
                ->where('kind', DailyCount::KIND_PAGE)
                ->sum('total');

            $detail = Event::query()
                ->where('type', EventType::Pageview)
                ->whereNotNull('page')
                ->whereBetween('occurred_at', [$day, $day->endOfDay()])
                ->whereHas('session', fn ($session) => $session->where('is_bot', false))
                ->count();

            $this->assertSame($detail, $summarised, "{$day->toDateString()} says what its detail says");
        }
    }
}
