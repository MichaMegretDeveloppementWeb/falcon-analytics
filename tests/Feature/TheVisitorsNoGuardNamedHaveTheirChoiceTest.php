<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Funnels\FunnelEvaluator;
use Falcon\Analytics\Livewire\Admin\OverviewPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewHeadline;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The visitors filter offers the visitors no guard named, beside the guards ·
 * each screen narrows on the column it already filters on, the session or the
 * visitor.
 */
final class TheVisitorsNoGuardNamedHaveTheirChoiceTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT = ['type' => 'client', 'id' => 1];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    public function test_the_filter_offers_them_after_the_guards(): void
    {
        Livewire::test(OverviewPage::class)
            ->assertViewHas('subjectOptions', ['' => 'Tous', 'client' => 'Client', 'lessor' => 'Loueur', 'none' => 'Non connectés'])
            ->assertSee('Non connectés');
    }

    public function test_their_name_is_the_hosts_to_choose(): void
    {
        config(['analytics.identity.anonymous_label' => 'Visiteurs du site']);

        Livewire::test(VisitorsPage::class)
            ->assertViewHas('subjectOptions', fn (array $options): bool => $options['none'] === 'Visiteurs du site')
            ->assertSee('Visiteurs du site');
    }

    /** Without a guard every visitor is a visitor no guard named, and the filter has nothing to offer. */
    public function test_without_a_declared_guard_the_filter_is_not_drawn(): void
    {
        config(['analytics.identity.subject_guards' => ['ghost']]);

        Livewire::test(OverviewPage::class)
            ->assertViewHas('subjectOptions', ['' => 'Tous'])
            ->assertDontSee('Non connectés')
            ->assertDontSeeHtml('wire:model.live="subject"');
    }

    public function test_the_sessions_figures_count_theirs(): void
    {
        $this->fourVisits();

        $this->assertSame(2.0, $this->sessionsCounted('none'));
        $this->assertSame(2.0, $this->sessionsCounted('client'));
        $this->assertSame(4.0, $this->sessionsCounted(''));
    }

    /**
     * A value the filter does not offer reads as everyone, never as a filter on nothing.
     *
     * @param  list<string>  $guards
     */
    #[DataProvider('valuesNotOffered')]
    public function test_a_value_not_offered_reads_as_everyone(array $guards, string $subject): void
    {
        $this->fourVisits();
        config(['analytics.identity.subject_guards' => $guards]);

        $this->assertSame(4.0, $this->sessionsCounted($subject));
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function valuesNotOffered(): array
    {
        return [
            'a guard that is not tracked' => [['client'], 'lessor'],
            'a guard the host never declared' => [['client', 'ghost'], 'ghost'],
            'a value made up' => [['client'], 'anyone'],
            'theirs, with no guard to stand beside' => [[], 'none'],
        ];
    }

    public function test_the_visitors_list_and_the_new_visitors_are_theirs(): void
    {
        $this->fourVisits();

        Livewire::test(VisitorsPage::class)
            ->set('subject', 'none')
            ->assertViewHas('visitors', fn ($visitors): bool => $visitors->total() === 2);

        $new = $this->app->make(VisitorListReadRepository::class)->visitorDailyRows(Period::ofDays(30), 'none')['new'] ?? [];
        ksort($new);

        $this->assertSame(['2026-07-07' => 1, '2026-07-10' => 1], $new);
    }

    public function test_the_named_events_are_theirs(): void
    {
        $this->fourVisits();

        $breakdown = $this->app->make(EventReadRepository::class)->eventBreakdown(Period::ofDays(30), 'none', $this->registry());

        $this->assertSame([['sample.action', 2, 2]], array_map(fn (array $row): array => [$row['name'], $row['count'], $row['visitors']], $breakdown));
    }

    /** The closed days from their summary, the day under way from its rows · both narrowed. */
    public function test_the_most_viewed_pages_are_theirs(): void
    {
        $this->fourVisits();
        $this->app->make(ArchiveClosedDaysAction::class)->execute();

        $pages = $this->app->make(OverviewReadRepository::class)->topPages(Period::ofDays(30), 'none');

        $this->assertEqualsCanonicalizing(['/hier-anonyme', '/accueil'], array_column($pages, 'label'));
    }

    public function test_the_realtime_figures_are_theirs(): void
    {
        $this->fourVisits();
        $realtime = $this->app->make(RealtimeReadRepository::class);
        $since = CarbonImmutable::now()->subMinutes(30);

        $this->assertSame(1, $realtime->onlineCount($since, 'none'));
        $this->assertSame(2, $realtime->onlineCount($since, null));
        $this->assertSame(1, $realtime->conversionsCount($since, 'none', ['sample.action']));
        $this->assertSame(2, $realtime->conversionsCount($since, null, ['sample.action']));
    }

    public function test_the_sessions_brought_by_an_ad_are_theirs(): void
    {
        $this->fourVisits();

        $rows = $this->app->make(MarketingReadRepository::class)->taggedSessionRows(Period::ofDays(30), 'none')->rows;

        $this->assertSame([null, null], $rows->pluck('subject_type')->all());
    }

    public function test_the_funnels_count_theirs(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->fourVisits();

        $report = ($this->app->make(FunnelEvaluator::class)->evaluateAll(Period::ofDays(30), 'none') ?? [])[0];

        $this->assertSame(2, $report->entrants);
        $this->assertSame(2, $report->steps[1]->visitors);
    }

    /**
     * Two visitors no guard named and two signed-in visits of one client, today
     * and three days ago · each with a page and a named event, arrived by an ad.
     */
    private function fourVisits(): void
    {
        $this->visit(null, '2026-07-10 11:55:00', '/accueil');
        $this->visit(null, '2026-07-07 10:00:00', '/hier-anonyme');
        $client = $this->visit(self::CLIENT, '2026-07-10 11:56:00', '/espace');
        $this->visit(self::CLIENT, '2026-07-07 10:05:00', '/espace-hier', $client->visitor);
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function visit(?array $subject, string $at, string $page, ?Visitor $visitor = null): Session
    {
        $moment = CarbonImmutable::parse($at);
        $visitor ??= Visitor::factory()->create([
            'first_seen_at' => $moment,
            'last_seen_at' => $moment,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);

        $session = Session::factory()->for($visitor)->at($moment)->create([
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'mkt_params' => ['utm_source' => 'meta'],
            'pageview_count' => 1,
        ]);

        Event::factory()->for($session)->create(['occurred_at' => $moment, 'url' => 'https://site.test'.$page, 'page' => $page, 'route' => 'home']);
        Event::factory()->for($session)->custom('sample.action')->create(['occurred_at' => $moment]);

        return $session;
    }

    private function sessionsCounted(string $subject): float
    {
        $sessions = null;

        Livewire::test(OverviewHeadline::class, ['period' => 30, 'subject' => $subject])->call('$refresh')
            ->assertViewHas('headline', function (array $headline) use (&$sessions): bool {
                $sessions = $headline['sessions']->current;

                return true;
            });

        return (float) $sessions;
    }

    private function registry(): EventRegistry
    {
        $registry = $this->app->make(EventRegistry::class);
        $registry->register(TrackedEvent::define('sample.action', 'Sample action', value: 5));

        return $registry;
    }
}
