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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Une page atteinte par plusieurs liens reste une page.
 *
 * **Mesuré le 2026-09-14.** La même page ouverte trois fois, dont deux par un
 * lien de campagne, revenait en trois lignes d'une vue — et l'écran affichait
 * le même chemin sur les trois, puisqu'il rend le chemin et groupait sur
 * l'adresse. Sur un site qui reçoit du trafic de campagne l'effet n'a rien de
 * discret : `fbclid` est unique par clic, donc la vraie page la plus vue se
 * divise en autant de lignes qu'elle a eu de visites et n'atteint jamais le
 * haut de la liste.
 *
 * Le défaut était antérieur aux résumés ; les résumés l'auraient rendu
 * définitif, puisqu'un compteur écrit de travers ne se recalcule plus une fois
 * les lignes effacées. Les trois lectures qui posent la question — la vue
 * d'ensemble, son résumé, et le temps réel — doivent donc y répondre pareil.
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
     * Une page ouverte par trois chemins différents · le lien nu, et deux liens
     * de campagne dont le jeton n'est jamais deux fois le même.
     */
    private function threeWaysToTheSamePage(CarbonImmutable $day): void
    {
        $session = $this->newSession();

        $this->pageview($session, 'https://exemple.fr/tarifs', $day->setTime(9, 0));
        $this->pageview($session, 'https://exemple.fr/tarifs?fbclid=IwAR0aaa', $day->setTime(9, 5));
        $this->pageview($session, 'https://exemple.fr/tarifs?fbclid=IwAR0bbb', $day->setTime(9, 10));

        // Une autre page, pour que le classement ait de quoi se tromper : avec
        // le regroupement fautif elle passait devant, à une vue contre trois.
        $this->pageview($session, 'https://exemple.fr/contact', $day->setTime(9, 15));
    }

    public function test_the_overview_counts_one_page_once(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->threeWaysToTheSamePage($day);

        $read = $this->overview->topPages(new Period($day->startOfDay(), $day->endOfDay(), 1), null, 20);

        $this->assertSame(
            [
                ['label' => 'https://exemple.fr/tarifs', 'total' => 3],
                ['label' => 'https://exemple.fr/contact', 'total' => 1],
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
        $this->threeWaysToTheSamePage($day);

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
                ['label' => 'https://exemple.fr/tarifs', 'total' => 3],
                ['label' => 'https://exemple.fr/contact', 'total' => 1],
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

        $this->pageview($session, 'https://exemple.fr/tarifs', CarbonImmutable::now()->subMinutes(10));
        $this->pageview($session, 'https://exemple.fr/tarifs?gclid=xyz', CarbonImmutable::now()->subMinutes(5));

        $read = $this->realtime->topPages(CarbonImmutable::now()->subMinutes(30), null);

        $this->assertSame([['url' => 'https://exemple.fr/tarifs', 'total' => 2]], $read);
    }

    /**
     * L'adresse entière reste écrite telle quelle, et le pas à pas d'une
     * session la montre entière · ce n'est pas la même question, et on ne perd
     * rien en la posant autrement ailleurs.
     */
    public function test_the_stored_address_keeps_its_query_string(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->threeWaysToTheSamePage($day);

        $this->assertTrue(
            Event::query()->where('url', 'https://exemple.fr/tarifs?fbclid=IwAR0aaa')->exists(),
            "L'adresse doit rester entière en base : seule la lecture groupe autrement.",
        );
    }

    /**
     * Une adresse sans point d'interrogation traverse l'expression sans y
     * perdre un caractère · c'est le cas ordinaire, et une troncature d'un
     * caractère y passerait inaperçue longtemps.
     */
    public function test_an_address_without_a_query_string_comes_back_whole(): void
    {
        $expression = StoredUrl::pathExpression(DB::connection()->getDriverName(), 'url');

        $day = CarbonImmutable::parse('2026-06-10');
        $this->pageview($this->newSession(), 'https://exemple.fr/a-propos', $day->setTime(9, 0));

        $read = DB::table(Event::TABLE)->selectRaw("{$expression} as label")->value('label');

        $this->assertSame('https://exemple.fr/a-propos', $read);
    }

    /**
     * Sur un moteur qu'il ne sait pas traiter, il lève plutôt que de retomber
     * sur l'adresse entière · un repli silencieux mettrait les chiffres d'un
     * moteur en désaccord avec ceux de tous les autres, et avec les résumés
     * écrits à côté.
     */
    public function test_an_unknown_engine_stops_rather_than_guesses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StoredUrl::pathExpression('oracle', 'url');
    }
}
