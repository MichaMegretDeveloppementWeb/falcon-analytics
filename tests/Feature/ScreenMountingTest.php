<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

/**
 * How a screen reaches the browser.
 *
 * Until 2026-09-07 each screen was a full-page Livewire component mounted by
 * `Route::livewire`, naming its own layout through `->layout()`. It now goes
 * route → controller → thin view → component, the way falcon/booking has always
 * done it, and the host layout is extended rather than filled as a slot.
 *
 * The suite's forty-four route assertions already prove the screens answer.
 * What they cannot see is where the screen lands, because they run against the
 * package's own shell. These do.
 */
final class ScreenMountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        config()->set('analytics.admin.layout', 'host-shell');
    }

    private function admin(): TestAdmin
    {
        return TestAdmin::create(['email' => 'admin@example.test']);
    }

    public function test_it_renders_the_screen_inside_the_section_of_the_host_layout(): void
    {
        /*
         * Both halves matter. The chrome alone would mean the section name is
         * wrong and the screen fell on the floor — silently, since Blade yields
         * an empty section without complaining. The screen alone would mean the
         * layout was never extended.
         *
         * The needle is taken from the body of the screen and nowhere else. The
         * screen's own name would not do: it is in the <title> too, so the
         * assertion held even with the section deliberately misnamed.
         */
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->assertSee('chrome fourni par le gabarit')
            ->assertSee(__('Visiteurs et appareils'));
    }

    /**
     * La feuille du kit accompagne celle du paquet, même sous un gabarit d'hôte
     * qui ne connaît rien de la suite.
     *
     * **Elle n'est pas décorative, elle est nécessaire** · les utilitaires du
     * paquet résolvent sept jetons du kit à l'exécution, `var(--ui-…)`, et ces
     * valeurs vivent dans la feuille du kit. Servie seule, celle du paquet
     * s'afficherait sans ses couleurs.
     *
     * Ce qui la fait venir n'est pas le gabarit — celui de la fixture est nu —
     * mais le contenu de l'écran, qui dessine des composants du kit. C'est ce
     * qui rend la séparation sûre · une feuille de paquet ne peut pas se
     * retrouver sur une page sans celle du kit.
     *
     * Le jour où un écran n'emploierait plus aucun composant du kit, cet essai
     * tombe — et il faudra alors décider, pas découvrir.
     */
    public function test_the_kit_sheet_travels_with_the_package_sheet(): void
    {
        $html = (string) $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertOk()
            ->getContent();

        foreach (['ui.css', 'analytics.css'] as $sheet) {
            $this->assertSame(1, substr_count($html, $sheet), "{$sheet} doit paraître une fois.");
        }
    }

    /**
     * The reason the conversion was worth doing.
     *
     * The error state used to carry a layout of its own, so a failed read
     * replaced the entire page. The controller decides the chrome now, and the
     * component only fills the section: what is lost is the panel, not the way
     * out of it.
     */
    public function test_it_keeps_the_host_chrome_when_the_screen_fails_to_read_its_data(): void
    {
        $admin = $this->admin();

        $this->withoutTable('falcon_analytics_sessions', function () use ($admin): void {
            $this->actingAs($admin, 'admin')
                ->get(route('analytics.admin.sessions'))
                ->assertOk()
                ->assertSee(__('Données indisponibles'))
                ->assertSee('chrome fourni par le gabarit');
        });
    }

    /** The title is composed by the controller, before the screen renders. */
    public function test_it_lets_the_controller_name_the_browser_tab(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.realtime'))
            ->assertSee('<title>'.__('Temps réel').' · '.__('Analytics').'</title>', false);
    }

    /**
     * Two screens name the record they show, and that title moved out of the
     * component when the screens were converted. The suite visits both already,
     * but only asserts on the body — this is the half that changed hands.
     */
    public function test_it_lets_the_controller_name_the_tab_after_the_record_it_shows(): void
    {
        $campaign = Campaign::create([
            'name' => 'Été 2026',
            'platform' => 'Meta',
            'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.marketing.campaigns.show', $campaign))
            ->assertSee('<title>Été 2026 · '.__('Marketing').'</title>', false);
    }

    /**
     * Le titre traverse deux composants, et il doit en sortir entier.
     *
     * **Blade échappe les attributs d'un composant de classe au moment où il les
     * pose**, parce qu'un attribut finit dans une balise. Un titre passé de
     * cette façon arrivait au gabarit déjà échappé et ressortait de `{{ }}`
     * échappé une seconde fois · `Vue d&amp;#039;ensemble` dans l'onglet du
     * navigateur, pendant que le reste de la page allait bien.
     *
     * Il voyage donc par le constructeur de la page, où c'est une donnée. Les
     * deux essais ci-dessus ne l'auraient jamais vu · aucun de leurs titres ne
     * porte d'apostrophe.
     */
    public function test_it_escapes_the_title_once_and_not_twice(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSee('<title>Vue d&#039;ensemble · '.__('Analytics').'</title>', false)
            ->assertDontSee('&amp;#039;', false);
    }
}
