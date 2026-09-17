<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use FilesystemIterator;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Un écran se cadre lui-même, sur son propre conteneur.
 *
 * La vue mince est l'endroit où un écran dit ce qu'il prend de la place qu'on
 * lui rend · `<x-analytics::page class="…">`. Ces classes traversent le sac
 * d'attributs jusqu'à `.an-root`, et cet élément est le seul endroit où une
 * largeur se décide. Le niveau au-dessus répond à une question — combien de
 * place y a-t-il — et jamais à la seconde.
 *
 * **Tenu ici parce que le chemin a été coupé une fois, par le dessus, sans que
 * rien ne tombe.** Une coquille portant sa propre largeur maximale a mis tous
 * les écrans de tous les paquets dans une colonne centrée ; une page qui perd
 * la moitié de sa largeur ne déborde pas, elle rétrécit, et aucune mesure ne
 * s'en est plainte.
 *
 * @see ScreenMountingTest pour la couche au-dessus · le gabarit de l'hôte,
 *      qui rend la place sans décider ce qu'on en fait.
 */
final class EachScreenFramesItselfTest extends TestCase
{
    /**
     * La classe est inventée plutôt que relue sur un écran · ce qui doit tenir
     * est qu'une déclaration arrive, pas la valeur que tel écran a retenue.
     */
    public function test_the_container_receives_what_the_screen_declares(): void
    {
        $html = Blade::render(
            '<x-analytics::page area="admin" class="an:max-w-[42em]">une page</x-analytics::page>'
        );

        $this->assertSame(1, preg_match('/<div\b[^>]*\bdata-area="admin"[^>]*>/', $html, $matches),
            "La page n'a pas rendu son conteneur.");

        $this->assertStringContainsString('an-root', $matches[0]);
        $this->assertStringContainsString('an:max-w-[42em]', $matches[0],
            "La classe déclarée par l'écran n'atteint plus son conteneur.");
    }

    /**
     * Et chaque écran le dit, sans exception.
     *
     * Ce qu'une vue muette produit ne ressemble pas à une erreur · la page
     * s'affiche, collée aux deux bords et sans marge intérieure, et rien ne le
     * signale. C'est l'écran suivant, celui qui n'existe pas encore, que cette
     * assertion attrape.
     */
    public function test_every_admin_screen_states_its_frame(): void
    {
        $views = $this->adminViews();

        $this->assertNotSame([], $views, 'Aucune vue lue : le chemin est faux.');

        foreach ($views as $view) {
            $markup = (string) file_get_contents($view->getPathname());

            $this->assertMatchesRegularExpression(
                '/<x-analytics::page\b[^>]*\sclass="[^"]+"/',
                $markup,
                $view->getFilename().' ne dit pas ce qu\'il prend de la place qu\'on lui rend.',
            );
        }
    }

    /**
     * Les vues minces de l'administration.
     *
     * @return list<SplFileInfo>
     */
    private function adminViews(): array
    {
        $found = [];

        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            dirname(__DIR__, 2).'/resources/views/admin',
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($walk as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file;
            }
        }

        return $found;
    }
}
