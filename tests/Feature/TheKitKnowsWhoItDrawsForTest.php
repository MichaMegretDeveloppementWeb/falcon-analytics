<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Admin\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * Le kit dessine pour quelqu'un, et ce quelqu'un doit être nommé.
 *
 * Un bouton du kit posé sur un écran d'analytics porte `data-ui-scope` et
 * `data-ui-area`. C'est ce que la feuille de style du paquet vise, et c'est
 * aussi ce qui permet au kit d'aller chercher un habillage propre au paquet
 * plutôt que sa propre vue. Rien de tout cela ne se déduit de l'URL ni d'un
 * compositeur : le kit lit une pile, pendant le rendu, et cette pile n'existe
 * que si une balise l'a ouverte.
 *
 * **Le deuxième essai est celui qui justifie la racine.** Une vue réactive est
 * recalculée seule après un clic, sans la page qui l'avait dessinée. Un
 * contexte posé par la seule page serait là à l'ouverture et absent au clic
 * suivant : l'apparence changerait après interaction, sans erreur nulle part.
 */
final class TheKitKnowsWhoItDrawsForTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_screen_draws_the_kit_inside_the_context(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            ->assertSee('data-ui-scope="analytics"', false)
            ->assertSee('data-ui-area="admin"', false);
    }

    /**
     * La mise à jour réactive, sans la page.
     *
     * `Livewire::test` rend le composant pour lui-même, exactement comme une
     * réponse à un clic : si le contexte ne venait que de la page, cette sortie
     * serait nue.
     */
    public function test_a_recomputed_fragment_keeps_the_context(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Livewire::withoutLazyLoading();

        $html = Livewire::test(OverviewSearchQueries::class)->html();

        $this->assertStringContainsString('data-ui-scope="analytics"', $html);
        $this->assertStringContainsString('data-ui-area="admin"', $html);
    }

    /**
     * Aucune vue réactive n'a été oubliée.
     *
     * La liste n'est pas écrite ici : elle se relève dans les composants
     * eux-mêmes, donc une vue ajoutée demain entre d'elle-même dans le contrôle.
     * Ce que l'essai ci-dessus prouve d'une vue, celui-ci l'étend à toutes.
     */
    public function test_every_view_a_component_returns_carries_a_root(): void
    {
        $without = [];

        foreach (self::viewsReturnedByComponents() as $view) {
            $path = __DIR__.'/../../resources/views/'
                .str_replace('.', '/', substr($view, strlen('analytics::')))
                .'.blade.php';

            $this->assertFileExists($path, "Le composant rend {$view}, qui n'existe pas.");

            if (! str_contains(File::get($path), '<x-analytics::root')) {
                $without[] = $view;
            }
        }

        $this->assertNotSame([], self::viewsReturnedByComponents(), 'Aucune vue relevée : le relevé est cassé.');
        $this->assertSame([], $without, 'Ces vues seront dessinées hors contexte après une interaction.');
    }

    /**
     * Les vues qu'un composant réactif peut rendre · écrans, blocs, gabarits
     * d'attente et vues d'erreur confondus.
     *
     * @return list<string>
     */
    private static function viewsReturnedByComponents(): array
    {
        $names = [];

        foreach (File::allFiles(__DIR__.'/../../src/Livewire') as $file) {
            preg_match_all("/view\('(analytics::[^']+)'/", File::get($file->getPathname()), $matches);

            $names = [...$names, ...$matches[1]];
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
