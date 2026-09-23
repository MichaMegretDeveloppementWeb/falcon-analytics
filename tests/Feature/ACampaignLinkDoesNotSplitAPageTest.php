<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Support\StoredUrl;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Une page atteinte par plusieurs liens reste une page.
 *
 * **Mesuré le 2026-09-14.** La même page ouverte trois fois, dont deux par un
 * lien de campagne, revenait en trois lignes d'une vue — et l'écran affichait
 * le même chemin sur les trois, puisqu'il rend le chemin et groupait sur
 * l'adresse. Sur un site qui reçoit du trafic de campagne l'effet n'a rien de
 * discret : `fbclid` est unique par clic, donc la vraie page la plus vue se
 * divise en autant de lignes qu'elle a eu de visites et n'atteint jamais le
 * haut de la liste. Les liens d'ancre font pareil avec `#`, et deux hôtes
 * servant un même site le feraient avec l'hôte.
 *
 * **Décidé le 2026-09-14 · une page, c'est le chemin de sa route.** Sans hôte,
 * sans paramètres, sans ancre. Il est écrit une fois, à l'arrivée, par la même
 * fonction que l'écran utilise pour afficher une adresse. Les trois lectures
 * qui posent la question — la vue d'ensemble, son résumé, et le temps réel —
 * groupent dessus, et l'adresse entière reste écrite pour le parcours.
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
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
        ]);
    }

    private function pageview(Session $session, string $url, CarbonImmutable $at): void
    {
        Event::create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => EventType::Pageview,
            'url' => $url,
            'occurred_at' => $at,
        ]);
    }

    /**
     * Une page ouverte par cinq chemins différents · le lien nu, deux liens de
     * campagne dont le jeton n'est jamais deux fois le même, un lien d'ancre,
     * et le même site servi sans le `www`.
     */
    private function fiveWaysToTheSamePage(CarbonImmutable $day): void
    {
        $session = $this->newSession();

        $this->pageview($session, 'https://www.exemple.test/tarifs', $day->setTime(9, 0));
        $this->pageview($session, 'https://www.exemple.test/tarifs?fbclid=IwAR0aaa', $day->setTime(9, 5));
        $this->pageview($session, 'https://www.exemple.test/tarifs?fbclid=IwAR0bbb', $day->setTime(9, 10));
        $this->pageview($session, 'https://www.exemple.test/tarifs#prix', $day->setTime(9, 12));
        $this->pageview($session, 'https://exemple.test/tarifs', $day->setTime(9, 14));

        // Une autre page, pour que le classement ait de quoi se tromper : avec
        // le regroupement fautif elle passait devant, à une vue contre cinq.
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

    /**
     * Et le résumé écrit la même chose, sans quoi le bloc sauterait le jour où
     * l'effacement traverse cette journée.
     */
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

    /**
     * Le temps réel pose la même question sur une fenêtre de trente minutes, et
     * doit donc y répondre pareil · deux écrans qui disent tous les deux « les
     * pages les plus vues » ne peuvent pas compter une page de deux façons.
     */
    public function test_the_realtime_screen_counts_one_page_once(): void
    {
        $session = $this->newSession();

        $this->pageview($session, 'https://exemple.test/tarifs', CarbonImmutable::now()->subMinutes(10));
        $this->pageview($session, 'https://exemple.test/tarifs?gclid=xyz', CarbonImmutable::now()->subMinutes(5));
        $this->pageview($session, 'https://exemple.test/tarifs#prix', CarbonImmutable::now()->subMinutes(2));

        $read = $this->realtime->topPages(CarbonImmutable::now()->subMinutes(30), null);

        $this->assertSame([['url' => '/tarifs', 'total' => 3]], $read);
    }

    /**
     * L'adresse entière reste écrite telle quelle, et le pas à pas d'une
     * session la montre entière · ce n'est pas la même question, et on ne perd
     * rien en la posant autrement ailleurs.
     */
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

    /**
     * La page est posée à côté de l'adresse dès l'écriture, par le modèle
     * comme par l'ingestion · une ligne ne peut pas porter une adresse et pas
     * de page.
     */
    public function test_a_row_written_through_the_model_carries_its_page(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->pageview($this->newSession(), 'https://exemple.test/a-propos?x=1#y', $day->setTime(9, 0));

        $this->assertSame('/a-propos', Event::query()->firstOrFail()->page);
    }

    /**
     * Ce que « la page » veut dire, cas par cas · le chemin, et rien d'autre.
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
