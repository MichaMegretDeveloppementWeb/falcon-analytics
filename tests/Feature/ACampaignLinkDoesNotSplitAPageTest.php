<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Support\StoredUrl;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A page reached through several links stays one page.
 *
 * A page is the path of its route: no host, no query string, no fragment. A
 * campaign token such as `fbclid` is unique per click, so grouping on the full
 * address would split a page into one row per visit. The overview, its summary
 * and the realtime screen group on the page; the full address stays stored.
 */
final class ACampaignLinkDoesNotSplitAPageTest extends TestCase
{
    use RefreshDatabase;

    private OverviewReadRepository $overview;

    private RealtimeReadRepository $realtime;

    private DailyCountArchiver $archiver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->overview = new OverviewReadRepository(new MarketingReportBuilder);
        $this->realtime = new RealtimeReadRepository;
        $this->archiver = new DailyCountArchiver;
    }

    private function newSession(): Session
    {
        return Session::factory()->create();
    }

    private function pageview(Session $session, string $url, CarbonImmutable $at): void
    {
        Event::factory()->for($session)->create(['url' => $url, 'occurred_at' => $at]);
    }

    /** One page reached bare, through two campaign links, an anchor, and without `www`. */
    private function fiveWaysToTheSamePage(CarbonImmutable $day): void
    {
        $session = $this->newSession();

        $this->pageview($session, 'https://www.exemple.test/tarifs', $day->setTime(9, 0));
        $this->pageview($session, 'https://www.exemple.test/tarifs?fbclid=IwAR0aaa', $day->setTime(9, 5));
        $this->pageview($session, 'https://www.exemple.test/tarifs?fbclid=IwAR0bbb', $day->setTime(9, 10));
        $this->pageview($session, 'https://www.exemple.test/tarifs#prix', $day->setTime(9, 12));
        $this->pageview($session, 'https://exemple.test/tarifs', $day->setTime(9, 14));

        // A second page, which a split page would fall below in the ranking.
        $this->pageview($session, 'https://www.exemple.test/contact', $day->setTime(9, 15));
    }

    public function test_the_overview_counts_one_page_once(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->fiveWaysToTheSamePage($day);

        $read = $this->overview->topPages(new Period($day->startOfDay(), $day->endOfDay(), 1), null, 20);

        $this->assertSame(
            [
                ['label' => '/tarifs', 'total' => 5],
                ['label' => '/contact', 'total' => 1],
            ],
            array_map(fn (array $row): array => ['label' => $row['label'], 'total' => $row['total']], $read),
        );
    }

    /** The summary agrees with the detail, or the block would change once erasure reaches the day. */
    public function test_the_summary_writes_one_row_for_that_page(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->fiveWaysToTheSamePage($day);

        $this->archiver->archive($day);

        $rows = DailyCount::query()
            ->where('day', $day->toDateString())
            ->where('kind', DailyCount::KIND_PAGE)
            ->orderByDesc('total')
            ->get(['label', 'total'])
            ->map(fn (DailyCount $row): array => ['label' => $row->label, 'total' => $row->total])
            ->all();

        $this->assertSame(
            [
                ['label' => '/tarifs', 'total' => 5],
                ['label' => '/contact', 'total' => 1],
            ],
            $rows,
        );
    }

    /** Two screens that both show the most viewed pages count a page the same way. */
    public function test_the_realtime_screen_counts_one_page_once(): void
    {
        $session = $this->newSession();

        $this->pageview($session, 'https://exemple.test/tarifs', CarbonImmutable::now()->subMinutes(10));
        $this->pageview($session, 'https://exemple.test/tarifs?gclid=xyz', CarbonImmutable::now()->subMinutes(5));
        $this->pageview($session, 'https://exemple.test/tarifs#prix', CarbonImmutable::now()->subMinutes(2));

        $read = $this->realtime->topPages(CarbonImmutable::now()->subMinutes(30), null);

        $this->assertSame([['url' => '/tarifs', 'total' => 3]], $read);
    }

    /** A session's step-by-step view shows the full address. */
    public function test_the_stored_address_keeps_its_query_string_and_fragment(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->fiveWaysToTheSamePage($day);

        $this->assertTrue(
            Event::query()->where('url', 'https://www.exemple.test/tarifs?fbclid=IwAR0aaa')->exists(),
            "L'adresse doit rester entière en base : seule la page est posée à côté.",
        );
        $this->assertTrue(Event::query()->where('url', 'https://www.exemple.test/tarifs#prix')->exists());
    }

    /** No row carries an address without its page, whichever path writes it. */
    public function test_a_row_written_through_the_model_carries_its_page(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->pageview($this->newSession(), 'https://exemple.test/a-propos?x=1#y', $day->setTime(9, 0));

        $this->assertSame('/a-propos', Event::query()->firstOrFail()->page);
    }

    /**
     * The page of an address, case by case: its path and nothing else.
     *
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function addresses(): array
    {
        return [
            'une adresse nue' => ['https://exemple.test/tarifs', '/tarifs'],
            'un lien de campagne' => ['https://exemple.test/tarifs?fbclid=IwAR0aaa', '/tarifs'],
            'un lien d’ancre' => ['https://exemple.test/tarifs#prix', '/tarifs'],
            'les deux' => ['https://exemple.test/tarifs?a=1#prix', '/tarifs'],
            'un autre hôte' => ['http://www.exemple.test/tarifs', '/tarifs'],
            'la racine' => ['https://exemple.test/', '/'],
            'un chemin profond, avec sa barre finale' => ['https://exemple.test/blog/mon-article/', '/blog/mon-article/'],
            'un chemin seul' => ['/tarifs?x=1', '/tarifs'],
            'rien' => ['', null],
            'null' => [null, null],
            'un hôte sans chemin' => ['https://exemple.test', null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_the_page_of_an_address_is_its_path(?string $url, ?string $page): void
    {
        $this->assertSame($page, StoredUrl::page($url));
    }
}
