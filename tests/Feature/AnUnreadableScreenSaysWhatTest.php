<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\AdsPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Admin\EventsPage;
use Falcon\Analytics\Livewire\Admin\FunnelsPage;
use Falcon\Analytics\Livewire\Admin\IntegrationsPage;
use Falcon\Analytics\Livewire\Admin\MarketingDashboardPage;
use Falcon\Analytics\Livewire\Admin\OverviewPage;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Livewire\Admin\Widgets\AdDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\CampaignDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\FunnelsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Livewire\Admin\Widgets\SessionsHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\TrendChart;
use Falcon\Analytics\Livewire\Admin\Widgets\VisitorsHeadline;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * A screen or a block that could not read its data says what is missing, in the
 * kit's drawing, and reads again on « Réessayer », without reloading the page.
 */
final class AnUnreadableScreenSaysWhatTest extends TestCase
{
    use RefreshDatabase;

    private const string WHAT_TO_DO = 'Réessayez dans un instant, et si cela continue, prévenez la personne qui s’occupe de votre site.';

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->admin = TestAdmin::create([]);
    }

    /**
     * Every guarded screen, and the title it shows when its read fails.
     *
     * @return array<class-string, array{class-string, string}>
     */
    public static function provideScreens(): array
    {
        $titles = [
            OverviewPage::class => 'Impossible de charger la vue d\'ensemble',
            RealtimePage::class => 'Impossible de charger le temps réel',
            VisitorsPage::class => 'Impossible de charger les visiteurs',
            VisitorDetailPage::class => 'Impossible de charger ce visiteur',
            SessionsPage::class => 'Impossible de charger les sessions',
            SessionDetailPage::class => 'Impossible de charger cette session',
            EventsPage::class => 'Impossible de charger les événements',
            FunnelsPage::class => 'Impossible de charger les tunnels',
            IntegrationsPage::class => 'Impossible de charger les intégrations',
            MarketingDashboardPage::class => 'Impossible de charger la vue d\'ensemble du marketing',
            CampaignsPage::class => 'Impossible de charger les campagnes',
            CampaignDetailPage::class => 'Impossible de charger cette campagne',
            AdsPage::class => 'Impossible de charger les publicités',
            AdDetailPage::class => 'Impossible de charger cette publicité',
            OverviewHeadline::class => 'Impossible de charger les chiffres de la période',
            VisitorsHeadline::class => 'Impossible de charger les chiffres des visiteurs',
            SessionsHeadline::class => 'Impossible de charger les chiffres des sessions',
            TrendChart::class => 'Impossible de charger la courbe du trafic',
            OverviewAcquisition::class => 'Impossible de charger les sources de trafic',
            OverviewAudience::class => 'Impossible de charger les visiteurs nouveaux et récurrents',
            OverviewContent::class => 'Impossible de charger les pages et les clics',
            OverviewEvents::class => 'Impossible de charger les événements et les conversions',
            OverviewSearchQueries::class => 'Impossible de charger les recherches Google',
            EventsContent::class => 'Impossible de charger les événements',
            FunnelsContent::class => 'Impossible de charger les tunnels',
            MarketingDashboardContent::class => 'Impossible de charger la vue d\'ensemble du marketing',
            CampaignDetailContent::class => 'Impossible de charger les résultats de cette campagne',
            AdDetailContent::class => 'Impossible de charger les résultats de cette publicité',
        ];

        $cases = [];

        foreach ($titles as $class => $title) {
            $cases[$class] = [$class, $title];
        }

        return $cases;
    }

    /**
     * @param  class-string  $screen
     */
    #[DataProvider('provideScreens')]
    public function test_every_screen_names_what_it_could_not_read(string $screen, string $title): void
    {
        $method = new ReflectionMethod($screen, 'unreadableTitle');

        $this->assertSame($title, $method->invoke((new ReflectionClass($screen))->newInstanceWithoutConstructor()));
    }

    /** A guarded screen added tomorrow cannot be left without its title here. */
    public function test_the_list_holds_every_guarded_screen(): void
    {
        $guarded = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src/Livewire'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(__DIR__.'/../../src/'), -4);
            $class = 'Falcon\\Analytics\\'.str_replace(['/', '\\'], '\\', $relative);

            if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if ($this->usesTrait($class, RecoversFromReadFailure::class) || $this->usesTrait($class, GuardsWidgetRead::class)) {
                $guarded[] = $class;
            }
        }

        sort($guarded);
        $listed = array_keys(self::provideScreens());
        sort($listed);

        $this->assertSame($listed, $guarded);
    }

    public function test_a_page_says_what_is_missing_and_reads_again(): void
    {
        $this->actingAs($this->admin, 'admin');
        Session::factory()->create(['pageview_count' => 1, 'city' => 'Genève']);

        $screen = Livewire::test(SessionsPage::class)->assertSee('Genève');

        $this->withoutTable('falcon_analytics_sessions', function () use ($screen): void {
            $screen->call('$refresh')
                ->assertSeeHtml('ui-load-failure')
                ->assertSeeHtml('an:rounded-xl an:border an:border-default an:bg-surface')
                ->assertSee('Impossible de charger les sessions')
                ->assertSee(self::WHAT_TO_DO)
                ->assertSeeHtml('wire:click="$refresh"')
                ->assertSee('Réessayer')
                ->assertDontSee('Données indisponibles');
        });

        $screen->call('$refresh')
            ->assertDontSee('Impossible de charger les sessions')
            ->assertSee('Genève');
    }

    public function test_a_block_says_what_is_missing_and_reads_again_on_its_own(): void
    {
        $this->actingAs($this->admin, 'admin');

        $block = Livewire::test(OverviewHeadline::class, ['period' => 30])->call('$refresh');

        $this->withoutTable('falcon_analytics_sessions', function () use ($block): void {
            $block->call('$refresh')
                ->assertSeeHtml('ui-load-failure')
                ->assertSee('Impossible de charger les chiffres de la période')
                ->assertSee(self::WHAT_TO_DO)
                ->assertSeeHtml('wire:click="$refresh"')
                ->assertDontSee('Données indisponibles');
        });

        $block->call('$refresh')
            ->assertDontSee('Impossible de charger les chiffres de la période')
            ->assertDontSeeHtml('ui-load-failure');
    }

    /** What a detail page reads beside its own row is read under the guard, from the first render. */
    public function test_a_session_whose_visitor_cannot_be_read_says_so(): void
    {
        $this->actingAs($this->admin, 'admin');
        $session = Session::factory()->create(['pageview_count' => 1]);

        $this->withoutTable('falcon_analytics_visitors', function () use ($session): void {
            Livewire::test(SessionDetailPage::class, ['session' => $session])
                ->assertSeeHtml('ui-load-failure')
                ->assertSee('Impossible de charger cette session');
        });
    }

    public function test_an_ad_whose_campaign_cannot_be_read_says_so(): void
    {
        $this->actingAs($this->admin, 'admin');
        $ad = Ad::factory()->create();

        $this->withoutTable('falcon_analytics_campaigns', function () use ($ad): void {
            Livewire::test(AdDetailPage::class, ['ad' => $ad])
                ->assertSeeHtml('ui-load-failure')
                ->assertSee('Impossible de charger cette publicité');
        });
    }

    /** @param class-string $class */
    private function usesTrait(string $class, string $trait): bool
    {
        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            if (in_array($trait, $reflection->getTraitNames(), true)) {
                return true;
            }
        }

        return false;
    }
}
